<?php 
namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentCourse;
use App\Models\AssignmentMezmur;
use App\Models\User;
use App\Models\Section;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    // Helper to check schedule conflicts
    protected function hasScheduleConflict($type, $dayOfWeek, $startTime, $endTime, $userOrTrainerId, $excludeAssignmentId = null)
    {
        $query = Assignment::where('day_of_week', $dayOfWeek)
                    ->where('type', $type)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);

        if ($type === 'Course') {
            $query->where('user_id', $userOrTrainerId);
        } elseif ($type === 'MezmurTraining') {
            $query->where('trainer_id', $userOrTrainerId);
        }

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->exists();
    }

    // List assignments (filtered by user role)
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Assignment::query()->with([
            'section',
            'trainer',
            'teacher',
            'mezmurs',
            'assignmentCourses.course',
            'assignmentCourses.teacher'
        ]);

        if ($user->hasRole('tmhrt_office_admin')) {
            $query->where('type', 'Course');
        } elseif ($user->hasRole('mezmur_office_admin')) {
            $query->where('type', 'MezmurTraining');
        } else {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($qwhere) use ($search) {
                $qwhere->where('id', $search)
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('trainer', fn($t) => $t->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacher', fn($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('assignmentCourses.course', fn($c) => $c->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('mezmurs', fn($m) => $m->where('title', 'like', "%{$search}%"));
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $results = $query->orderBy($sortBy, $sortDir)
                         ->paginate($perPage)
                         ->withQueryString();

        return response()->json($results);
    }

    // Show single assignment
    public function show(Assignment $assignment)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($assignment->type === 'Course' && !$user->hasRole('tmhrt_office_admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($assignment->type === 'MezmurTraining' && !$user->hasRole('mezmur_office_admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $assignment->load([
            'section',
            'trainer',
            'teacher',
            'mezmurs',
            'assignmentCourses.course',
            'assignmentCourses.teacher'
        ]);

        return response()->json($assignment);
    }

    // Store new assignment
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->hasRole('tmhrt_office_admin')) {
            // Validate Course assignment input
            $rules = [
                'section' => 'required|string',
                'user_id' => 'required|exists:users,id',
                'course' => 'required|string',
                'default_period_order' => 'nullable|integer',
                'location' => 'nullable|string',
                'day_of_week' => 'required|integer|min:0|max:6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ];
            $validated = $request->validate($rules);

            // Check user role
            $assignedUser = User::find($validated['user_id']);
            if (!$assignedUser || !$assignedUser->hasRole('teacher')) {
                return response()->json(['message' => 'Assigned user must have the teacher role'], 422);
            }

            // Check section and course exist
            $section = Section::where('name', $validated['section'])->first();
            if (!$section) {
                return response()->json(['message' => 'Section not found'], 422);
            }
            $course = Course::where('name', $validated['course'])->first();
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 422);
            }

            // Check program type match
            if ($section->program_type_id !== $course->program_type_id) {
                return response()->json(['message' => 'Section and Course program types do not match'], 422);
            }

            // Check schedule conflict
            if ($this->hasScheduleConflict('Course', $validated['day_of_week'], $validated['start_time'], $validated['end_time'], $validated['user_id'])) {
                return response()->json(['message' => 'Schedule conflict: Teacher has another assignment at this time'], 422);
            }

            // Create assignment and related assignment_course
            DB::beginTransaction();
            try {
                $assignment = Assignment::create([
                    'type' => 'Course',
                    'section_id' => $section->id,
                    'user_id' => $validated['user_id'],
                    'location' => $validated['location'] ?? null,
                    'day_of_week' => $validated['day_of_week'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'active' => true,
                ]);

                AssignmentCourse::create([
                    'assignment_id' => $assignment->id,
                    'course_id' => $course->id,
                    'teacher_id' => $validated['user_id'],
                    'default_period_order' => $validated['default_period_order'] ?? null,
                ]);

                DB::commit();

                $assignment->load([
                    'section',
                    'trainer',
                    'teacher',
                    'mezmurs',
                    'assignmentCourses.course',
                    'assignmentCourses.teacher'
                ]);

                return response()->json($assignment, 201);
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json(['message' => 'Could not create assignment', 'error' => $e->getMessage()], 500);
            }
        } 
        elseif ($user->hasRole('mezmur_office_admin')) {
            // Validate MezmurTraining assignment input
            $rules = [
                'trainer_id' => 'required|exists:trainers,id',
                'mezmur_ids' => 'required|array|min:1',
                'mezmur_ids.*' => 'exists:mezmurs,id',
                'location' => 'nullable|string',
                'day_of_week' => 'required|integer|min:0|max:6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ];
            $validated = $request->validate($rules);

            // Check schedule conflict for trainer
            if ($this->hasScheduleConflict('MezmurTraining', $validated['day_of_week'], $validated['start_time'], $validated['end_time'], $validated['trainer_id'])) {
                return response()->json(['message' => 'Schedule conflict: Trainer has another assignment at this time'], 422);
            }

            DB::beginTransaction();
            try {
                $assignment = Assignment::create([
                    'type' => 'MezmurTraining',
                    'trainer_id' => $validated['trainer_id'],
                    'location' => $validated['location'] ?? null,
                    'day_of_week' => $validated['day_of_week'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'active' => true,
                ]);

                foreach ($validated['mezmur_ids'] as $mid) {
                    AssignmentMezmur::create([
                        'assignment_id' => $assignment->id,
                        'mezmur_id' => $mid,
                    ]);
                }

                DB::commit();

                $assignment->load([
                    'section',
                    'trainer',
                    'teacher',
                    'mezmurs',
                    'assignmentCourses.course',
                    'assignmentCourses.teacher'
                ]);

                return response()->json($assignment, 201);
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json(['message' => 'Could not create assignment', 'error' => $e->getMessage()], 500);
            }
        } 
        else {
            return response()->json(['message' => 'Your role cannot create assignments'], 403);
        }
    }

    // Update assignment
    public function update(Request $request, Assignment $assignment)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($assignment->type === 'Course' && !$user->hasRole('tmhrt_office_admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($assignment->type === 'MezmurTraining' && !$user->hasRole('mezmur_office_admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($assignment->type === 'Course') {
            $rules = [
                'section' => 'sometimes|required|string',
                'user_id' => 'required|exists:users,id',
                'course' => 'required|string',
                'default_period_order' => 'nullable|integer',
                'location' => 'nullable|string',
                'day_of_week' => 'required|integer|min:0|max:6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'active' => 'nullable|boolean',
            ];

            $data = $request->validate($rules);

            $assignedUser = User::find($data['user_id']);
            if (!$assignedUser || !$assignedUser->hasRole('teacher')) {
                return response()->json(['message' => 'Assigned user must have the teacher role'], 422);
            }

            $section = Section::where('name', $data['section'])->first();
            if (!$section) {
                return response()->json(['message' => 'Section not found'], 422);
            }
            $course = Course::where('name', $data['course'])->first();
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 422);
            }

            if ($section->program_type_id !== $course->program_type_id) {
                return response()->json(['message' => 'Section and Course program types do not match'], 422);
            }

            // Check schedule conflict excluding current assignment
            if ($this->hasScheduleConflict('Course', $data['day_of_week'], $data['start_time'], $data['end_time'], $data['user_id'], $assignment->id)) {
                return response()->json(['message' => 'Schedule conflict: Teacher has another assignment at this time'], 422);
            }

            DB::transaction(function () use ($assignment, $data, $section, $course) {
                $assignment->update([
                    'section_id' => $section->id,
                    'user_id' => $data['user_id'],
                    'location' => $data['location'] ?? $assignment->location,
                    'day_of_week' => $data['day_of_week'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'active' => $data['active'] ?? $assignment->active,
                ]);

                $ac = $assignment->assignmentCourses()->first();
                if ($ac) {
                    $ac->update([
                        'course_id' => $course->id,
                        'teacher_id' => $data['user_id'],
                        'default_period_order' => $data['default_period_order'] ?? $ac->default_period_order,
                    ]);
                } else {
                    AssignmentCourse::create([
                        'assignment_id' => $assignment->id,
                        'course_id' => $course->id,
                        'teacher_id' => $data['user_id'],
                        'default_period_order' => $data['default_period_order'] ?? null,
                    ]);
                }
            });

        } else {
            // MezmurTraining update
            $rules = [
                'trainer_id' => 'required|exists:trainers,id',
                'mezmur_ids' => 'required|array|min:1',
                'mezmur_ids.*' => 'exists:mezmurs,id',
                'location' => 'nullable|string',
                'day_of_week' => 'required|integer|min:0|max:6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'active' => 'nullable|boolean',
            ];

            $data = $request->validate($rules);

            if ($this->hasScheduleConflict('MezmurTraining', $data['day_of_week'], $data['start_time'], $data['end_time'], $data['trainer_id'], $assignment->id)) {
                return response()->json(['message' => 'Schedule conflict: Trainer has another assignment at this time'], 422);
            }

            DB::transaction(function () use ($assignment, $data) {
                $assignment->update([
                    'trainer_id' => $data['trainer_id'],
                    'location' => $data['location'] ?? $assignment->location,
                    'day_of_week' => $data['day_of_week'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'active' => $data['active'] ?? $assignment->active,
                ]);

                // Delete old mezmurs and add new ones
                $assignment->mezmurs()->sync($data['mezmur_ids']);
            });
        }

        $assignment->load([
            'section',
            'trainer',
            'teacher',
            'mezmurs',
            'assignmentCourses.course',
            'assignmentCourses.teacher'
        ]);

        return response()->json($assignment);
    }

    // Delete assignment
    public function destroy(Assignment $assignment)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (($assignment->type === 'Course' && !$user->hasRole('tmhrt_office_admin')) ||
            ($assignment->type === 'MezmurTraining' && !$user->hasRole('mezmur_office_admin'))
        ) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted successfully']);
    }

    // Public schedule view (all assignments per day)
    public function schedule(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $dayOfWeek = $request->input('day_of_week');

        $query = Assignment::with([
            'section',
            'trainer',
            'teacher',
            'mezmurs',
            'assignmentCourses.course',
            'assignmentCourses.teacher'
        ])->where('active', true);

        if ($dayOfWeek !== null) {
            $query->where('day_of_week', $dayOfWeek);
        }

        

        $schedule = $query->orderBy('day_of_week')->orderBy('start_time')->get();

        return response()->json($schedule);
    }
}
