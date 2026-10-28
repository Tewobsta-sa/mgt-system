<?php

namespace App\Http\Controllers;

use App\Models\MezmurExam;
use App\Models\MezmurExamResult;
use App\Models\Student;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MezmurExamController extends Controller
{
    /**
     * List all Mezmur exams with results counts
     */
    public function index(Request $request)
    {
        $exams = MezmurExam::with(['creator:id,name', 'ministry:id,name,location,ministry_date'])
            ->withCount([
                'results',
                'results as passed_count' => fn($q) => $q
                    ->where('status', 'passed')
                    ->whereHas('student', fn($student) => $student
                        ->whereIn('status', ['regular', 'Active'])
                        ->whereNotIn('status', ['new', 'Inactive', 'Graduated'])),
                'results as failed_count' => fn($q) => $q
                    ->where('status', 'failed')
                    ->whereHas('student', fn($student) => $student
                        ->whereIn('status', ['regular', 'Active'])
                        ->whereNotIn('status', ['new', 'Inactive', 'Graduated'])),
                'results as sent_count' => fn($q) => $q->where('sent_to_yesew_habt', true),
            ])
            ->orderBy('exam_date', 'desc')
            ->paginate(15);

        return response()->json($exams);
    }

    /**
     * Create a new Mezmur exam
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ministry_id' => 'nullable|exists:ministries,id',
            'target_classification' => 'nullable|string|in:prekg,htsanat,maekelawyan,wetatoch,distance',
            'title' => 'required|string|max:255',
            'exam_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $exam = MezmurExam::create([
            'ministry_id' => $validated['ministry_id'] ?? null,
            'target_classification' => $validated['target_classification'] ?? null,
            'title' => $validated['title'],
            'exam_date' => $validated['exam_date'],
            'description' => $validated['description'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json($exam->load(['creator:id,name', 'ministry:id,name,location,ministry_date']), 201);
    }

    /**
     * Show a single exam with all student results
     */
    public function show($id)
    {
        $exam = MezmurExam::with(['creator:id,name', 'ministry:id,name,location,ministry_date', 'results.student.section'])->findOrFail($id);
        return response()->json($exam);
    }

    /**
     * Get students who attended Mezmur classes to populate the exam candidate list.
     * Enforces the rule: ONLY regular students (not 'new') can participate in Mezmur exams.
     */
    public function getAttendees(Request $request)
    {
        $query = Student::notFlagged()
            ->with('section')
            ->whereIn('status', ['regular', 'Active'])
            ->whereNotIn('status', ['new', 'Inactive', 'Graduated']);

        // Target group: explicit param, or resolved from the exam itself.
        $target = $request->input('target_classification');
        if (!$target && $request->filled('exam_id')) {
            $target = MezmurExam::whereKey($request->input('exam_id'))->value('target_classification');
        }
        if ($target && $target !== 'all') {
            $query->where('classification', $target);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('christian_name', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('name')->get();

        return response()->json($students);
    }

    /**
     * Bulk save or update exam results (scores & pass/fail status)
     */
    public function bulkSaveResults(Request $request)
    {
        $validated = $request->validate([
            'mezmur_exam_id' => 'required|exists:mezmur_exams,id',
            'results' => 'required|array',
            'results.*.student_id' => 'required|exists:students,id',
            'results.*.score' => 'nullable|numeric|min:0|max:100',
            'results.*.status' => 'required|in:passed,failed,pending',
            'results.*.notes' => 'nullable|string',
        ], [], [
            'mezmur_exam_id' => 'exam',
            'results.*.student_id' => 'student',
            'results.*.score' => 'score',
            'results.*.status' => 'result status',
            'results.*.notes' => 'notes',
        ]);

        $examId = $validated['mezmur_exam_id'];
        $now = now();
        $saved = [];

        DB::transaction(function () use ($examId, $validated, $now, &$saved) {
            foreach ($validated['results'] as $item) {
                $result = MezmurExamResult::updateOrCreate(
                    [
                        'mezmur_exam_id' => $examId,
                        'student_id' => $item['student_id'],
                    ],
                    [
                        'score' => $item['score'] ?? null,
                        'status' => $item['status'],
                        'notes' => $item['notes'] ?? null,
                        'updated_at' => $now,
                    ]
                );
                $saved[] = $result;
            }
        });

        return response()->json([
            'message' => 'Exam results saved successfully.',
            'count' => count($saved),
        ]);
    }

    /**
     * Bulk send passed students to Yesew Habt for ministry assignment
     */
    public function bulkSendPassedToYesewHabt(Request $request)
    {
        $validated = $request->validate([
            'mezmur_exam_id' => 'nullable|exists:mezmur_exams,id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ], [], [
            'mezmur_exam_id' => 'exam',
            'student_ids' => 'students',
            'student_ids.*' => 'student',
        ]);

        // Only regular (not new, not flagged) students can be forwarded
        $regularStudentIds = Student::whereIn('id', $validated['student_ids'])
            ->where('status', 'regular')
            ->where('status', '!=', 'new')
            ->where('is_flagged', false)
            ->pluck('id');

        $query = MezmurExamResult::whereIn('student_id', $regularStudentIds)
            ->where('status', 'passed');

        if (!empty($validated['mezmur_exam_id'])) {
            $query->where('mezmur_exam_id', $validated['mezmur_exam_id']);
        }

        $count = $query->update([
            'sent_to_yesew_habt' => true,
            'sent_at' => now(),
        ]);

        // Also ensure regular students are marked is_mezmur = true
        Student::whereIn('id', $regularStudentIds)->update(['is_mezmur' => true]);

        return response()->json([
            'message' => "{$count} passed regular students successfully forwarded to Yesew Habt.",
            'count' => $count,
        ]);
    }

    /**
     * Fetch passed students ready for Yesew Habt to assign to ministries
     */
    public function getPassedStudentsForYesewHabt(Request $request)
    {
        $search = $request->input('search');

        $query = MezmurExamResult::with(['student.section', 'student.address', 'exam.ministry:id,name,location,ministry_date'])
            ->where('sent_to_yesew_habt', true)
            ->where('status', 'passed')
            ->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('ministry_assignment_students as mas')
                    ->join('ministry_assignments as ma', 'ma.id', '=', 'mas.ministry_assignment_id')
                    ->whereColumn('mas.student_id', 'mezmur_exam_results.student_id');
            })
            ->whereHas('student', function ($q) {
                $q->where('status', 'regular')
                  ->where('status', '!=', 'new')
                  ->where('is_flagged', false);
            });

        if ($search) {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('christian_name', 'like', "%{$search}%");
            });
        }

        $results = $query->orderBy('sent_at', 'desc')->paginate(20);

        $since = \App\Support\EthiopianDate::academicYearStart();
        $minAttendance = (float) config('academic.mezmur_min_attendance', 70.0);

        // Attach this-year mezmur attendance % to each passed row.
        $studentsById = Student::with('attendances.assignment')
            ->whereIn('id', $results->getCollection()->pluck('student_id')->filter())
            ->get()
            ->keyBy('id');

        $results->getCollection()->transform(function ($r) use ($studentsById, $minAttendance, $since) {
            $pct = ($st = $studentsById->get($r->student_id))
                ? \App\Services\PromotionEvaluator::mezmurAttendancePercent($st, $since)
                : null;
            $r->mezmur_attendance_pct = $pct;
            $r->attendance_eligible = $pct !== null && $pct >= $minAttendance;
            return $r;
        });

        // View-only list: regular students eligible purely by mezmur
        // attendance (>= threshold this academic year) who have neither been
        // sent as passed nor already assigned to a ministry.
        $sentPassedIds = MezmurExamResult::where('status', 'passed')
            ->where('sent_to_yesew_habt', true)
            ->pluck('student_id');
        $assignedIds = \App\Models\MinistryAssignmentStudent::pluck('student_id');

        $eligibleStudents = Student::notFlagged()
            ->with(['section', 'attendances.assignment'])
            ->where('status', 'regular')
            ->whereNotIn('id', $sentPassedIds)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get()
            ->map(function ($s) use ($since, $minAttendance) {
                $pct = \App\Services\PromotionEvaluator::mezmurAttendancePercent($s, $since);
                if ($pct === null || $pct < $minAttendance) {
                    return null;
                }
                return [
                    'id' => $s->id,
                    'student_id' => $s->student_id,
                    'name' => $s->name,
                    'christian_name' => $s->christian_name,
                    'section_name' => $s->section?->name,
                    'classification' => $s->classification,
                    'mezmur_attendance_pct' => $pct,
                ];
            })
            ->filter()
            ->values();

        $payload = $results->toArray();
        $payload['eligible_students'] = $eligibleStudents;
        $payload['mezmur_min_attendance'] = $minAttendance;
        $payload['academic_year_start'] = $since;

        return response()->json($payload);
    }
}
