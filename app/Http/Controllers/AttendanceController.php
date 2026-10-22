<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function markAttendance(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'assignment_id' => 'required|exists:assignments,id',
            'student_id' => 'required|exists:students,id',
            'status' => 'required|in:Present,Absent,Excused,Late',
            'session_date' => 'nullable|date',
        ]);

        $assignment = Assignment::with('section')->findOrFail($validated['assignment_id']);
        $student = Student::with('section')->findOrFail($validated['student_id']);

        $canMark = $user->hasRole('super_admin') 
            || $user->hasRole('yesew_habt') 
            || ($user->hasRole('tmhrt_kfl') && $assignment->type === 'Course') 
            || ($user->hasRole('mezmur_kfl') && $assignment->type === 'MezmurTraining') 
            || ($user->hasRole('teacher') && $assignment->assignmentCourses()->where('teacher_id', $user->id)->exists());

        if (!$canMark) {
            return response()->json([
                'message' => 'Forbidden: You do not have permission to record attendance for this session.'
            ], 403);
        }

        if (!$assignment->section && $assignment->type === 'Course') {
            return response()->json(['message' => 'Assignment does not have a valid section for program type check.'], 422);
        }

        // For course attendance, ensure student belongs to the same section as the assignment.
        if ($assignment->type === 'Course' && !is_null($assignment->section_id) && (int) $student->section_id !== (int) $assignment->section_id) {
            return response()->json([
                'message' => 'Section mismatch: Student is not in the assigned section for this course.'
            ], 422);
        }

        // Night shift validation: Ensure student and assignment shifts match
        if ((bool) $student->is_night !== (bool) $assignment->is_night) {
            $sessionShift = $assignment->is_night ? 'የማታ (Night)' : 'የቀን (Day)';
            $studentShift = $student->is_night ? 'የማታ (Night)' : 'የቀን (Day)';
            return response()->json([
                'message' => "የፈረቃ ልዩነት (Shift Mismatch): ይህ ክፍለ-ጊዜ {$sessionShift} ነው። ተማሪ {$student->name} ግን የተመዘገበው በ{$studentShift} ፈረቃ ነው።"
            ], 422);
        }

        $status = $validated['status'];
        $sessionDate = $validated['session_date'] ?? $request->input('session_date') ?? $assignment->scheduled_date ?? now()->toDateString();

        $attendance = Attendance::updateOrCreate(
            [
                'assignment_id' => $validated['assignment_id'],
                'student_id' => $validated['student_id'],
                'session_date' => $sessionDate,
            ],
            [
                'status' => $status,
                'late_minutes' => null,
                'marked_by_user_id' => $user->id,
                'marked_at' => now(),
            ]
        );

        return response()->json([
            'message' => "Attendance recorded ({$status})",
            'attendance' => $attendance,
            'status' => $status
        ], 200);
    }

    public function getAttendance(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Attendance::with([
            'student',
            'markedBy',
            'assignment.section',
            'assignment.trainer',
            'assignment.teacher',
            'assignment.assignmentCourses.course',
            'assignment.mezmurs',
        ]);

        if ($user->hasRole('super_admin') || $user->hasRole('yesew_habt') || $user->hasRole('gngnunet_office_admin')) {
            // Super admin and Ye Sew Habt can view all attendance
        } elseif ($user->hasRole('tmhrt_kfl') || $user->hasRole('tmhrt_office_admin')) {
            // Tmhrt Kfl: view Course related attendance only
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'Course');
            });
        } elseif ($user->hasRole('mezmur_kfl') || $user->hasRole('mezmur_office_admin')) {
            // Mezmur Kfl: view Mezmur related attendance only
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'MezmurTraining');
            });
        } elseif ($user->hasRole('teacher')) {
            $query->whereHas('assignment', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('assignmentCourses', function ($assignmentCourse) use ($user) {
                        $assignmentCourse->where('teacher_id', $user->id);
                    });
            });
        } else {
            return response()->json(['message' => 'Forbidden: Your role cannot view attendance.'], 403);
        }

        if ($sectionId = $request->input('section_id')) {
            $query->whereHas('assignment', function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId);
            });
        }

        if ($courseId = $request->input('course_id')) {
            $query->whereHas('assignment.assignmentCourses', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        if ($assignmentId = $request->input('assignment_id')) {
            $query->where('assignment_id', $assignmentId);
        }

        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }

        if ($request->has('is_night') && $request->input('is_night') !== '' && $request->input('is_night') !== 'all') {
            $isNight = filter_var($request->input('is_night'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('assignment', function ($q) use ($isNight) {
                $q->where('is_night', $isNight);
            });
        }

        if ($status = $request->input('status')) {
            $allowedStatuses = ['Present', 'Absent', 'Excused', 'Late'];
            if (in_array($status, $allowedStatuses)) {
                $query->where('status', $status);
            }
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate && $endDate) {
            $query->whereBetween('marked_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } elseif ($startDate) {
            $query->where('marked_at', '>=', $startDate . ' 00:00:00');
        } elseif ($endDate) {
            $query->where('marked_at', '<=', $endDate . ' 23:59:59');
        }

        $perPage = $request->input('per_page', 20);

        $attendanceRecords = $query->orderBy('marked_at', 'desc')->paginate($perPage);

        return response()->json($attendanceRecords);
    }

    /**
     * 📱 Fast mobile camera scan and attendance mark
     */
    public function scanAndMark(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!($user->hasRole('super_admin') || $user->hasRole('yesew_habt'))) {
            return response()->json([
                'message' => 'Forbidden: Only Ye Sew Habt (የሰው ሀብት ክፍል) or Super Admin can scan and record attendance.'
            ], 403);
        }

        $validated = $request->validate([
            'assignment_id' => 'required|exists:assignments,id',
            'qr_data' => 'required',
            'status' => 'nullable|in:Present,Absent,Excused,Late',
        ]);

        $status = $validated['status'] ?? 'Present';
        $qrRaw = trim($validated['qr_data']);

        // 1. Resolve student ID from QR payload
        $studentId = null;
        $decoded = json_decode($qrRaw, true);

        if (is_array($decoded)) {
            $studentId = $decoded['id'] ?? null;
            if (!$studentId && isset($decoded['sid'])) {
                $found = Student::where('student_id', $decoded['sid'])->first();
                $studentId = $found?->id;
            }
        } elseif (is_numeric($qrRaw)) {
            $studentId = (int) $qrRaw;
        } else {
            // Check by student_id formatted string (e.g. REG/1 or YNG/1)
            $found = Student::where('student_id', $qrRaw)->first();
            $studentId = $found?->id;
        }

        if (!$studentId) {
            return response()->json([
                'message' => 'Unrecognized QR code or student not found: ' . substr($qrRaw, 0, 40)
            ], 404);
        }

        $student = Student::with(['section.programType'])->find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        // 2. Load Assignment and check permissions
        $assignment = Assignment::with(['section', 'assignmentCourses.course', 'mezmurs'])->findOrFail($validated['assignment_id']);

        if ($user->hasRole('teacher')) {
            $isOwnAssignment = $assignment->assignmentCourses()
                ->where('teacher_id', $user->id)
                ->exists();
            if (!$isOwnAssignment) {
                return response()->json(['message' => 'Forbidden: You are not assigned to teach this class.'], 403);
            }
        }

        // Program and section validation
        if ($assignment->type === 'Course' && $assignment->section_id && (int) $student->section_id !== (int) $assignment->section_id) {
            return response()->json([
                'message' => "Section mismatch: {$student->name} belongs to " . ($student->section?->name ?? 'another section') . ", not {$assignment->section?->name}."
            ], 422);
        }

        // Night shift validation: Ensure student and assignment shifts match
        if ((bool) $student->is_night !== (bool) $assignment->is_night) {
            $sessionShift = $assignment->is_night ? 'የማታ (Night)' : 'የቀን (Day)';
            $studentShift = $student->is_night ? 'የማታ (Night)' : 'የቀን (Day)';
            return response()->json([
                'message' => "የፈረቃ ልዩነት (Shift Mismatch): ይህ ክፍለ-ጊዜ {$sessionShift} ሲሆን ተማሪ {$student->name} ግን የተመዘገበው በ{$studentShift} ፈረቃ ነው።"
            ], 422);
        }

        // 3. Record attendance
        $sessionDate = $request->input('session_date') ?? $assignment->scheduled_date ?? now()->toDateString();

        $attendance = Attendance::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'session_date' => $sessionDate,
            ],
            [
                'status' => $status,
                'late_minutes' => null,
                'marked_by_user_id' => $user->id,
                'marked_at' => now(),
            ]
        );

        $feedbackMsg = "{$student->name} marked as {$status}!";

        return response()->json([
            'message' => $feedbackMsg,
            'attendance' => $attendance,
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->name,
                'christian_name' => $student->christian_name,
                'section_name' => $student->section?->name ?? 'Unassigned',
                'picture_url' => $student->picture_url,
                'status' => $status,
                'late_minutes' => null,
                'marked_at' => now()->format('H:i:s'),
            ],
        ], 200);
    }

    /**
     * 📋 Get students and live attendance status for a specific assignment session
     */
    public function getMobileSessionStudents(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $assignmentId = $request->input('assignment_id');
        if (!$assignmentId) {
            return response()->json(['message' => 'assignment_id is required'], 422);
        }

        $assignment = Assignment::with([
            'section.programType',
            'assignmentCourses.course',
            'mezmurs',
            'teacher',
            'trainer'
        ])->findOrFail($assignmentId);

        if ($user->hasRole('teacher')) {
            $isOwnAssignment = $assignment->user_id === $user->id
                || $assignment->assignmentCourses()->where('teacher_id', $user->id)->exists();
            if (!$isOwnAssignment) {
                return response()->json(['message' => 'Forbidden: You are not assigned to this class.'], 403);
            }
        }

        // Fetch students eligible for this assignment
        $studentsQuery = Student::query();

        if ($assignment->type === 'Course' && $assignment->section_id) {
            $studentsQuery->where('section_id', $assignment->section_id);
        } elseif ($assignment->type === 'MezmurTraining') {
            // Only regular mezmur students
            $studentsQuery->where('is_mezmur', true);
        }

        // Night schedule strictly expects night students; Day schedule expects day students
        if ($assignment->is_night) {
            $studentsQuery->where('is_night', true);
        } else {
            $studentsQuery->where('is_night', false);
        }

        $students = $studentsQuery->notFlagged()->orderBy('name', 'asc')->get();

        $sessionDate = $request->input('session_date') ?? $assignment->scheduled_date ?? now()->toDateString();

        // Fetch existing attendance records for this session date
        $attendances = Attendance::where('assignment_id', $assignment->id)
            ->where('session_date', $sessionDate)
            ->get()
            ->keyBy('student_id');

        $roster = [];
        $presentCount = 0;
        $lateCount = 0;
        $absentCount = 0;
        $excusedCount = 0;

        foreach ($students as $st) {
            $att = $attendances->get($st->id);
            $status = $att ? $att->status : 'Unmarked';

            if ($status === 'Present') $presentCount++;
            elseif ($status === 'Late') $lateCount++;
            elseif ($status === 'Absent') $absentCount++;
            elseif ($status === 'Excused') $excusedCount++;

            $roster[] = [
                'id' => $st->id,
                'student_id' => $st->student_id,
                'name' => $st->name,
                'christian_name' => $st->christian_name,
                'picture_url' => $st->picture_url,
                'status' => $status,
                'late_minutes' => $att?->late_minutes,
                'marked_at' => $att?->marked_at ? date('H:i', strtotime($att->marked_at)) : null,
            ];
        }

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'type' => $assignment->type,
                'is_night' => (bool) $assignment->is_night,
                'title' => $assignment->type === 'Course' 
                    ? ($assignment->assignmentCourses->first()?->course?->name ?? 'Course') 
                    : ($assignment->mezmurs->first()?->title ?? 'Mezmur Training'),
                'section_name' => $assignment->section?->name ?? 'All Sections',
                'scheduled_date' => $assignment->scheduled_date,
                'start_time' => $assignment->start_time,
                'end_time' => $assignment->end_time,
            ],
            'stats' => [
                'total' => count($students),
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'excused' => $excusedCount,
                'unmarked' => count($students) - ($presentCount + $lateCount + $absentCount + $excusedCount),
            ],
            'students' => $roster,
        ]);
    }

    /**
     * 👥 Bulk mark attendance for multiple selected students
     */
    public function bulkMark(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'assignment_id' => 'required|exists:assignments,id',
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'required|exists:students,id',
            'status'        => 'required|in:Present,Absent,Excused',
            'session_date'  => 'nullable|date',
        ], [], [
            'assignment_id' => 'assignment',
            'student_ids'   => 'students',
            'status'        => 'attendance status',
            'session_date'  => 'session date',
        ]);

        $assignment = Assignment::with(['section', 'assignmentCourses'])->findOrFail($validated['assignment_id']);

        $canMark = $user->hasRole('super_admin') 
            || $user->hasRole('yesew_habt') 
            || ($user->hasRole('tmhrt_kfl') && $assignment->type === 'Course') 
            || ($user->hasRole('mezmur_kfl') && $assignment->type === 'MezmurTraining') 
            || ($user->hasRole('teacher') && $assignment->assignmentCourses()->where('teacher_id', $user->id)->exists());

        if (!$canMark) {
            return response()->json([
                'message' => 'Forbidden: You do not have permission to record attendance for this session.'
            ], 403);
        }

        $sessionDate = $validated['session_date'] ?? $request->input('session_date') ?? $assignment->scheduled_date ?? now()->toDateString();
        $status = $validated['status'];
        $now = now();
        $savedCount = 0;

        DB::transaction(function () use ($validated, $assignment, $sessionDate, $status, $now, $user, &$savedCount) {
            foreach ($validated['student_ids'] as $studentId) {
                Attendance::updateOrCreate(
                    [
                        'assignment_id' => $assignment->id,
                        'student_id'    => $studentId,
                        'session_date'  => $sessionDate,
                    ],
                    [
                        'status'            => $status,
                        'late_minutes'      => null,
                        'marked_by_user_id' => $user->id,
                        'marked_at'         => $now,
                    ]
                );
                $savedCount++;
            }
        });

        return response()->json([
            'message' => "Successfully marked {$savedCount} student(s) as {$status}.",
            'saved_count' => $savedCount,
            'status' => $status,
            'session_date' => $sessionDate,
        ], 200);
    }
}
