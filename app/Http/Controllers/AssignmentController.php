<?php 
namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentCourse;
use App\Models\AssignmentMezmur;
use App\Models\User;
use App\Models\Section;
use App\Models\Course;
use App\Models\Trainer;
use App\Models\Mezmur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    protected function hasScheduleConflict($type, $dayOfWeek, $startTime, $endTime, $userOrTrainerId, $excludeAssignmentId = null, $scheduledDate = null)
    {
        $query = Assignment::where('type', $type);

        // If both values are present, treat it as recurring and match by weekday.
        if (!is_null($dayOfWeek) && $dayOfWeek !== '') {
            $query->where('day_of_week', $dayOfWeek);
        } elseif (!is_null($scheduledDate) && $scheduledDate !== '') {
            $query->where('scheduled_date', $scheduledDate);
        }

        if ($type === 'Course') {
            $query->where('user_id', $userOrTrainerId);
        } elseif ($type === 'MezmurTraining') {
            $query->where('trainer_id', $userOrTrainerId);
        }

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        if ($startTime < $endTime) {
            $query->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($sameDay) use ($startTime, $endTime) {
                    $sameDay->whereColumn('start_time', '<', 'end_time')
                            ->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                })->orWhere(function ($overnight) use ($startTime, $endTime) {
                    $overnight->whereColumn('start_time', '>=', 'end_time')
                              ->where(function ($sub) use ($startTime, $endTime) {
                                  $sub->where('start_time', '<', $endTime)
                                      ->orWhere('end_time', '>', $startTime);
                              });
                });
            });
        } else {
            // Overnight session (e.g. 22:00 to 02:00)
            $query->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '>=', $startTime)
                  ->orWhere('end_time', '<=', $endTime)
                  ->orWhere('start_time', '<', $endTime)
                  ->orWhere('end_time', '>', $startTime);
            });
        }

        return $query->exists();
    }

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

        if ($user->hasRole('super_admin') || $user->hasRole('mereja_kfl') || $user->hasRole('yesew_habt')) {
            // Can see everything
        } elseif ($user->hasRole('tmhrt_kfl')) {
            $query->where('type', 'Course');
        } elseif ($user->hasRole('mezmur_kfl')) {
            $query->where('type', 'MezmurTraining');
        } else {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->has('is_night') && $request->input('is_night') !== '' && $request->input('is_night') !== 'all') {
            $query->where('is_night', filter_var($request->input('is_night'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = $request->input('q')) {
            $query->where(function ($qwhere) use ($search) {
                $qwhere->where('id', $search)
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('trainer', fn($t) => $t->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacher', fn($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('assignmentCourses.course', fn($c) => $c->where('name', 'like', "%{$search}%"))
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

    public function show(Assignment $assignment)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($assignment->type === 'Course') {
            $canView = $user->hasRole('tmhrt_kfl') || $user->hasRole('super_admin') || $user->hasRole('mereja_kfl') || $user->hasRole('yesew_habt');
            if (! $canView) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }
        if ($assignment->type === 'MezmurTraining' && !($user->hasRole('mezmur_kfl') || $user->hasRole('super_admin') || $user->hasRole('mereja_kfl') || $user->hasRole('yesew_habt'))) {
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

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$request->filled('day_of_week') && !$request->filled('scheduled_date')) {
            return response()->json(['message' => 'Either day_of_week or scheduled_date is required'], 422);
        }

        if ($user->hasRole('mezmur_kfl') && !$user->hasRole('super_admin')) {
            goto mezmur_branch;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('tmhrt_kfl')) {
            if ($request->type === 'MezmurTraining' && $user->hasRole('super_admin')) {
                goto mezmur_branch;
            }

            $rules = [
                'section_id' => 'nullable|exists:sections,id',
                'section' => 'required_without:section_id|string',
                'user_id' => 'required|exists:users,id',
                'course_id' => 'nullable|exists:courses,id',
                'course' => 'required_without:course_id|string',
                'default_period_order' => 'nullable|integer',
                'location' => 'nullable|string',
                'day_of_week' => 'nullable|integer|min:0|max:6',
                'scheduled_date' => 'nullable|date',
                'is_night' => 'nullable|boolean',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|different:start_time',
            ];
            $validated = $request->validate($rules);
            if (!filter_var($validated['is_night'] ?? false, FILTER_VALIDATE_BOOLEAN) && $validated['end_time'] <= $validated['start_time']) {
                return response()->json(['message' => 'Day schedule end time must be after start time'], 422);
            }
            $isRecurring = !is_null($validated['day_of_week'] ?? null) && ($validated['day_of_week'] ?? '') !== '';

            // Normalize mutually exclusive fields so conflict detection is deterministic.
            if ($isRecurring) {
                $validated['scheduled_date'] = null;
            } else {
                $validated['day_of_week'] = null;
            }

            $assignedUser = User::find($validated['user_id']);
            if (!$assignedUser || !($assignedUser->hasRole('tmhrt_kfl') || $assignedUser->hasRole('super_admin') || $assignedUser->hasRole('teacher'))) {
                return response()->json(['message' => 'Assigned user must belong to Tmhrt Kfl'], 422);
            }

            if ($validated['section_id'] ?? null) {
                $section = Section::find($validated['section_id']);
            } else {
                $section = Section::where('name', $validated['section'])->first();
            }

            if (!$section) {
                return response()->json(['message' => 'Section not found'], 422);
            }

            if ($validated['course_id'] ?? null) {
                $course = Course::find($validated['course_id']);
            } else {
                $course = Course::where('name', $validated['course'])->first();
            }

            if (!$course) {
                return response()->json(['message' => 'Course not found'], 422);
            }

            // Check that section and course program types match if both specified
            if ($section->program_type_id && $course->program_type_id && $section->program_type_id !== $course->program_type_id) {
                return response()->json(['message' => 'Section and Course program types do not match'], 422);
            }

            if ($this->hasScheduleConflict(
                'Course', 
                $validated['day_of_week'] ?? null, 
                $validated['start_time'], 
                $validated['end_time'], 
                $validated['user_id'], 
                null, 
                $validated['scheduled_date'] ?? null
            )) {
                return response()->json(['message' => 'Schedule conflict: Teacher has another assignment at this time'], 422);
            }

            DB::beginTransaction();
            try {
                $assignment = Assignment::create([
                    'type' => 'Course',
                    'is_night' => filter_var($validated['is_night'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'section_id' => $section->id,
                    'user_id' => $validated['user_id'],
                    'location' => $validated['location'] ?? null,
                    'day_of_week' => $validated['day_of_week'] ?? null,
                    'scheduled_date' => $validated['scheduled_date'] ?? null,
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
        mezmur_branch:
        if ($user->hasRole('super_admin') || $user->hasRole('mezmur_kfl')) {
            $rules = [
                'trainer_id' => 'required|exists:trainers,id',
                'mezmur_ids' => 'required|array|min:1',
                'mezmur_ids.*' => 'exists:mezmurs,id',
                'location' => 'nullable|string',
                'day_of_week' => 'nullable|integer|min:0|max:6',
                'scheduled_date' => 'nullable|date',
                'is_night' => 'nullable|boolean',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|different:start_time',
            ];
            $validated = $request->validate($rules);
            if (!filter_var($validated['is_night'] ?? false, FILTER_VALIDATE_BOOLEAN) && $validated['end_time'] <= $validated['start_time']) {
                return response()->json(['message' => 'Day schedule end time must be after start time'], 422);
            }
            $isRecurring = !is_null($validated['day_of_week'] ?? null) && ($validated['day_of_week'] ?? '') !== '';

            if ($isRecurring) {
                $validated['scheduled_date'] = null;
            } else {
                $validated['day_of_week'] = null;
            }

            $trainer = Trainer::find($validated['trainer_id']);
            if (!$trainer) {
                return response()->json(['message' => 'Trainer not found'], 422);
            }

            $mezmurs = Mezmur::whereIn('id', $validated['mezmur_ids'])->get();

            foreach ($mezmurs as $mezmur) {
                if (!in_array($mezmur->category_type, $trainer->specialties ?? [])) {
                    return response()->json([
                        'message' => "Trainer specialty mismatch: Trainer does not have the required specialty '{$mezmur->category_type}' for mezmur '{$mezmur->title}'."
                    ], 422);
                }
            }

            if ($this->hasScheduleConflict(
                'MezmurTraining', 
                $validated['day_of_week'] ?? null, 
                $validated['start_time'], 
                $validated['end_time'], 
                $validated['trainer_id'], 
                null, 
                $validated['scheduled_date'] ?? null
            )) {
                return response()->json(['message' => 'Schedule conflict: Trainer has another assignment at this time'], 422);
            }

            DB::beginTransaction();
            try {
                $assignment = Assignment::create([
                    'type' => 'MezmurTraining',
                    'is_night' => filter_var($validated['is_night'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'trainer_id' => $validated['trainer_id'],
                    'location' => $validated['location'] ?? null,
                    'day_of_week' => $validated['day_of_week'] ?? null,
                    'scheduled_date' => $validated['scheduled_date'] ?? null,
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
            if (!$user->hasRole('super_admin')) {
                return response()->json(['message' => 'Your role cannot create assignments'], 403);
            }
            // If super_admin reached here without specifically hitting a branch
            return response()->json(['message' => 'Invalid assignment type specified'], 400);
        }
    }

    public function update(Request $request, Assignment $assignment)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($assignment->type === 'Course' && !($user->hasRole('tmhrt_kfl') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($assignment->type === 'MezmurTraining' && !($user->hasRole('mezmur_kfl') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$request->filled('day_of_week') && !$request->filled('scheduled_date')) {
            return response()->json(['message' => 'Either day_of_week or scheduled_date is required'], 422);
        }

        if ($assignment->type === 'Course') {
            $rules = [
                'section_id' => 'nullable|exists:sections,id',
                'section' => 'sometimes|required_without:section_id|string',
                'user_id' => 'required|exists:users,id',
                'course_id' => 'nullable|exists:courses,id',
                'course' => 'required_without:course_id|string',
                'default_period_order' => 'nullable|integer',
                'location' => 'nullable|string',
                'day_of_week' => 'nullable|integer|min:0|max:6',
                'scheduled_date' => 'nullable|date',
                'is_night' => 'nullable|boolean',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|different:start_time',
                'active' => 'nullable|boolean',
            ];

            $data = $request->validate($rules);
            $checkIsNight = isset($data['is_night']) ? filter_var($data['is_night'], FILTER_VALIDATE_BOOLEAN) : (bool) $assignment->is_night;
            if (!$checkIsNight && $data['end_time'] <= $data['start_time']) {
                return response()->json(['message' => 'Day schedule end time must be after start time'], 422);
            }
            $isRecurring = !is_null($data['day_of_week'] ?? null) && ($data['day_of_week'] ?? '') !== '';

            if ($isRecurring) {
                $data['scheduled_date'] = null;
            } else {
                $data['day_of_week'] = null;
            }

            $assignedUser = User::find($data['user_id']);
            if (!$assignedUser || !($assignedUser->hasRole('tmhrt_kfl') || $assignedUser->hasRole('super_admin') || $assignedUser->hasRole('teacher'))) {
                return response()->json(['message' => 'Assigned user must belong to Tmhrt Kfl'], 422);
            }

            if ($data['section_id'] ?? null) {
                $section = Section::find($data['section_id']);
            } else {
                $section = Section::where('name', $data['section'])->first();
            }

            if (!$section) {
                return response()->json(['message' => 'Section not found'], 422);
            }

            if ($data['course_id'] ?? null) {
                $course = Course::find($data['course_id']);
            } else {
                $course = Course::where('name', $data['course'])->first();
            }

            if (!$course) {
                return response()->json(['message' => 'Course not found'], 422);
            }

            // Check that section and course program types match if both specified
            if ($section->program_type_id && $course->program_type_id && $section->program_type_id !== $course->program_type_id) {
                return response()->json(['message' => 'Section and Course program types do not match'], 422);
            }

            if ($this->hasScheduleConflict(
                'Course', 
                $data['day_of_week'] ?? null, 
                $data['start_time'], 
                $data['end_time'], 
                $data['user_id'], 
                $assignment->id, 
                $data['scheduled_date'] ?? null
            )) {
                return response()->json(['message' => 'Schedule conflict: Teacher has another assignment at this time'], 422);
            }

            DB::transaction(function () use ($assignment, $data, $section, $course) {
                $assignment->update([
                    'section_id' => $section->id,
                    'is_night' => isset($data['is_night']) ? filter_var($data['is_night'], FILTER_VALIDATE_BOOLEAN) : $assignment->is_night,
                    'user_id' => $data['user_id'],
                    'location' => $data['location'] ?? $assignment->location,
                    'day_of_week' => $data['day_of_week'] ?? null,
                    'scheduled_date' => $data['scheduled_date'] ?? null,
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
            $rules = [
                'trainer_id' => 'required|exists:trainers,id',
                'mezmur_ids' => 'required|array|min:1',
                'mezmur_ids.*' => 'exists:mezmurs,id',
                'location' => 'nullable|string',
                'day_of_week' => 'nullable|integer|min:0|max:6',
                'scheduled_date' => 'nullable|date',
                'is_night' => 'nullable|boolean',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|different:start_time',
                'active' => 'nullable|boolean',
            ];

            $data = $request->validate($rules);
            $checkIsNight = isset($data['is_night']) ? filter_var($data['is_night'], FILTER_VALIDATE_BOOLEAN) : (bool) $assignment->is_night;
            if (!$checkIsNight && $data['end_time'] <= $data['start_time']) {
                return response()->json(['message' => 'Day schedule end time must be after start time'], 422);
            }
            $isRecurring = !is_null($data['day_of_week'] ?? null) && ($data['day_of_week'] ?? '') !== '';

            if ($isRecurring) {
                $data['scheduled_date'] = null;
            } else {
                $data['day_of_week'] = null;
            }

            $trainer = Trainer::find($data['trainer_id']);
            if (!$trainer) {
                return response()->json(['message' => 'Trainer not found'], 422);
            }

            $mezmurs = Mezmur::whereIn('id', $data['mezmur_ids'])->get();

            foreach ($mezmurs as $mezmur) {
                if (!in_array($mezmur->category_type, $trainer->specialties ?? [])) {
                    return response()->json([
                        'message' => "Trainer specialty mismatch: Trainer does not have the required specialty '{$mezmur->category_type}' for mezmur '{$mezmur->title}'."
                    ], 422);
                }
            }

            if ($this->hasScheduleConflict(
                'MezmurTraining', 
                $data['day_of_week'] ?? null, 
                $data['start_time'], 
                $data['end_time'], 
                $data['trainer_id'], 
                $assignment->id, 
                $data['scheduled_date'] ?? null
            )) {
                return response()->json(['message' => 'Schedule conflict: Trainer has another assignment at this time'], 422);
            }

            DB::transaction(function () use ($assignment, $data) {
                $assignment->update([
                    'trainer_id' => $data['trainer_id'],
                    'is_night' => isset($data['is_night']) ? filter_var($data['is_night'], FILTER_VALIDATE_BOOLEAN) : $assignment->is_night,
                    'location' => $data['location'] ?? $assignment->location,
                    'day_of_week' => $data['day_of_week'] ?? null,
                    'scheduled_date' => $data['scheduled_date'] ?? null,
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'active' => $data['active'] ?? $assignment->active,
                ]);

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

    public function destroy(Assignment $assignment)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($assignment->type === 'Course' && !($user->hasRole('tmhrt_kfl') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($assignment->type === 'MezmurTraining' && !($user->hasRole('mezmur_kfl') || $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $assignment->delete();

        return response()->json(null, 204);
    }

    public function getSchedule(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $dayOfWeek = $request->input('day_of_week');

        $now = Carbon::now()->startOfDay();

        Assignment::whereNotNull('scheduled_date')
            ->where('scheduled_date', '<', $now)
            ->where('active', true)
            ->update(['active' => false]);

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

        if ($request->has('is_night') && $request->input('is_night') !== '' && $request->input('is_night') !== 'all') {
            $query->where('is_night', filter_var($request->input('is_night'), FILTER_VALIDATE_BOOLEAN));
        }

        $schedule = $query->orderBy('day_of_week')->orderBy('start_time')->get();

        return response()->json($schedule);
    }

    public function schedule(Request $request)
    {
        return $this->getSchedule($request);
    }
}
