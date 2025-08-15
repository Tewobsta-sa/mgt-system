<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\MinistryAssignment;
use App\Models\Student;
use App\Models\Mezmur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MinistryController extends Controller
{
    protected function requireMezmurOfficeUser()
    {
        $user = Auth::user();
        if (!$user) abort(401, 'Unauthorized');

        if (!($user->hasRole('mezmur_office_admin') || $user->hasRole('mezmur_office_coordinator'))) {
            abort(403, 'Forbidden: Mezmur office role required.');
        }

        return $user;
    }

    protected function runAutoAssignment(MinistryAssignment $assignment, int $threshold = 60): array
    {
        $start = $assignment->duration_start_date . ' 00:00:00';
        $end   = $assignment->duration_end_date   . ' 23:59:59';

        $rows = DB::table('attendances as a')
            ->join('assignments as asg', 'asg.id', '=', 'a.assignment_id')
            ->join('students as s', 's.id', '=', 'a.student_id')
            ->selectRaw("
                a.student_id,
                COUNT(*) as total_sessions,
                SUM(CASE WHEN a.status IN ('Present','Excused') THEN 1 ELSE 0 END) as attended_sessions
            ")
            ->where('s.is_mezmur', true)
            ->where('asg.type', 'MezmurTraining')
            ->whereBetween('a.marked_at', [$start, $end])
            ->groupBy('a.student_id')
            ->get();

        $now = now();
        $toInsert = [];
        $considered = 0;

        foreach ($rows as $r) {
            $considered++;
            if ($r->total_sessions <= 0) continue;

            $rate = ($r->attended_sessions / $r->total_sessions) * 100;
            if ($rate >= $threshold) {
                $toInsert[] = [
                    'ministry_assignment_id' => $assignment->id,
                    'student_id' => $r->student_id,
                    'source' => 'Auto',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            DB::table('ministry_assignment_students')->upsert(
                $toInsert,
                ['ministry_assignment_id', 'student_id'],
                ['source', 'updated_at']
            );
        }

        return ['inserted' => count($toInsert), 'considered' => $considered];
    }

    public function index(Request $request)
    {
        $this->requireMezmurOfficeUser();

        $q = MinistryAssignment::query()
            ->with(['ministry:id,name', 'mezmurs:id,title', 'creator:id,name'])
            ->withCount('students');

        if ($request->filled('q')) {
            $term = $request->query('q');
            $q->whereHas('ministry', fn($w) => $w->where('name', 'like', "%{$term}%"))
              ->orWhere('id', $term);
        }

        if ($request->filled('ministry_id')) {
            $q->where('ministry_id', $request->query('ministry_id'));
        }

        $start = $request->query('start_date');
        $end   = $request->query('end_date');
        if ($start && $end) {
            $q->where(function ($w) use ($start, $end) {
                $w->whereBetween('duration_start_date', [$start, $end])
                  ->orWhereBetween('duration_end_date', [$start, $end])
                  ->orWhere(function ($i) use ($start, $end) {
                      $i->where('duration_start_date', '<=', $start)
                        ->where('duration_end_date', '>=', $end);
                  });
            });
        } elseif ($start) {
            $q->where('duration_end_date', '>=', $start);
        } elseif ($end) {
            $q->where('duration_start_date', '<=', $end);
        }

        if ($request->filled('created_by')) {
            $q->where('created_by_user_id', $request->query('created_by'));
        }

        $perPage = (int) $request->query('per_page', 20);

        return $q->latest('id')->paginate($perPage);
    }

    public function show($id)
    {
        $this->requireMezmurOfficeUser();

        $assignment = MinistryAssignment::with([
            'ministry:id,name',
            'mezmurs:id,title',
            'students' => function ($q) {
                $q->select('students.id', 'students.name', 'students.student_id')
                  ->withPivot('source', 'created_at', 'updated_at');
            },
            'creator:id,name',
        ])->findOrFail($id);

        return response()->json($assignment);
    }

    public function store(Request $request)
    {
        $user = $this->requireMezmurOfficeUser();

        $validated = $request->validate([
            'ministry_id' => 'required|exists:ministries,id',
            'duration_start_date' => 'required|date',
            'duration_end_date' => 'required|date|after_or_equal:duration_start_date',
            'mezmur_ids' => 'required|array|min:1',
            'mezmur_ids.*' => 'exists:mezmurs,id',
            'auto_threshold' => 'nullable|integer|min:1|max:100',
            'run_auto' => 'nullable|boolean',
            'manual_student_ids' => 'nullable|array',
            'manual_student_ids.*' => 'exists:students,id',
        ]);

        return DB::transaction(function () use ($validated, $user) {
            $assignment = MinistryAssignment::create([
                'ministry_id' => $validated['ministry_id'],
                'duration_start_date' => $validated['duration_start_date'],
                'duration_end_date' => $validated['duration_end_date'],
                'created_by_user_id' => $user->id,
            ]);

            $assignment->mezmurs()->sync($validated['mezmur_ids']);

            if (!empty($validated['manual_student_ids'])) {
                $now = now();
                $manual = array_map(function ($sid) use ($assignment, $now) {
                    return [
                        'ministry_assignment_id' => $assignment->id,
                        'student_id' => $sid,
                        'source' => 'Manual',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $validated['manual_student_ids']);

                DB::table('ministry_assignment_students')->upsert(
                    $manual,
                    ['ministry_assignment_id', 'student_id'],
                    ['source', 'updated_at']
                );
            }

            $runAuto = array_key_exists('run_auto', $validated) ? (bool)$validated['run_auto'] : true;
            $threshold = $validated['auto_threshold'] ?? 60;
            $autoResult = ['inserted' => 0, 'considered' => 0];

            if ($runAuto) {
                $autoResult = $this->runAutoAssignment($assignment, $threshold);
            }

            return response()->json([
                'message' => 'Ministry assignment created',
                'assignment' => $assignment->load(['ministry:id,name','mezmurs:id,title']),
                'auto' => $autoResult,
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $this->requireMezmurOfficeUser();

        $validated = $request->validate([
            'ministry_id' => 'sometimes|required|exists:ministries,id',
            'duration_start_date' => 'sometimes|required|date',
            'duration_end_date' => 'sometimes|required|date|after_or_equal:duration_start_date',
            'mezmur_ids' => 'sometimes|required|array|min:1',
            'mezmur_ids.*' => 'exists:mezmurs,id',
            'auto_threshold' => 'nullable|integer|min:1|max:100',
            'rerun_auto' => 'nullable|boolean',
            'manual_student_ids_add' => 'nullable|array',
            'manual_student_ids_add.*' => 'exists:students,id',
            'manual_student_ids_remove' => 'nullable|array',
            'manual_student_ids_remove.*' => 'exists:students,id',
        ]);

        $assignment = MinistryAssignment::findOrFail($id);

        return DB::transaction(function () use ($validated, $assignment) {

            if (isset($validated['ministry_id'])) {
                $assignment->ministry_id = $validated['ministry_id'];
            }
            if (isset($validated['duration_start_date'])) {
                $assignment->duration_start_date = $validated['duration_start_date'];
            }
            if (isset($validated['duration_end_date'])) {
                $assignment->duration_end_date = $validated['duration_end_date'];
            }
            $assignment->save();

            if (isset($validated['mezmur_ids'])) {
                $assignment->mezmurs()->sync($validated['mezmur_ids']);
            }

            if (!empty($validated['manual_student_ids_add'])) {
                $now = now();
                $manualAdd = array_map(function ($sid) use ($assignment, $now) {
                    return [
                        'ministry_assignment_id' => $assignment->id,
                        'student_id' => $sid,
                        'source' => 'Manual',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $validated['manual_student_ids_add']);

                DB::table('ministry_assignment_students')->upsert(
                    $manualAdd,
                    ['ministry_assignment_id', 'student_id'],
                    ['source', 'updated_at']
                );
            }

            if (!empty($validated['manual_student_ids_remove'])) {
                DB::table('ministry_assignment_students')
                    ->where('ministry_assignment_id', $assignment->id)
                    ->whereIn('student_id', $validated['manual_student_ids_remove'])
                    ->delete();
            }

            $autoResult = null;
            if (!empty($validated['rerun_auto'])) {
                $threshold = $validated['auto_threshold'] ?? 60;
                $autoResult = $this->runAutoAssignment($assignment, $threshold);
            }

            return response()->json([
                'message' => 'Ministry assignment updated',
                'assignment' => $assignment->load(['ministry:id,name','mezmurs:id,title']),
                'auto' => $autoResult,
            ]);
        });
    }

    public function addStudents(Request $request, $id)
    {
        $this->requireMezmurOfficeUser();

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $assignment = MinistryAssignment::findOrFail($id);

        $now = now();
        $rows = array_map(function ($sid) use ($assignment, $now) {
            return [
                'ministry_assignment_id' => $assignment->id,
                'student_id' => $sid,
                'source' => 'Manual',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $validated['student_ids']);

        DB::table('ministry_assignment_students')->upsert(
            $rows,
            ['ministry_assignment_id', 'student_id'],
            ['source', 'updated_at']
        );

        return response()->json(['message' => 'Students added (Manual).']);
    }

    public function removeStudents(Request $request, $id)
    {
        $this->requireMezmurOfficeUser();

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $assignment = MinistryAssignment::findOrFail($id);

        DB::table('ministry_assignment_students')
            ->where('ministry_assignment_id', $assignment->id)
            ->whereIn('student_id', $validated['student_ids'])
            ->delete();

        return response()->json(['message' => 'Students removed.']);
    }

    public function rerunAutoAssign(Request $request, $id)
    {
        $this->requireMezmurOfficeUser();

        $validated = $request->validate([
            'threshold' => 'nullable|integer|min:1|max:100'
        ]);

        $assignment = MinistryAssignment::findOrFail($id);
        $result = $this->runAutoAssignment($assignment, $validated['threshold'] ?? 60);

        return response()->json([
            'message' => 'Auto assignment completed',
            'result' => $result
        ]);
    }

    public function destroy($id)
    {
        $this->requireMezmurOfficeUser();

        $assignment = MinistryAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json(null, 204);
    }
}
