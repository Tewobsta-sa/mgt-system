<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'status' => 'required|in:Present,Absent,Excused',
        ]);

        $assignment = Assignment::with('section')->findOrFail($validated['assignment_id']);
        $student = Student::with('section')->findOrFail($validated['student_id']);

        if ($user->hasRole('mezmur_office_admin') || $user->hasRole('mezmur_office_coordinator')) {
            if ($assignment->type !== 'MezmurTraining') {
                return response()->json(['message' => 'Forbidden: You can only mark attendance for MezmurTraining assignments.'], 403);
            }
        } elseif ($user->hasRole('tmhrt_office_admin') || $user->hasRole('tmhrt_office_coordinator')) {
            if ($assignment->type !== 'Course') {
                return response()->json(['message' => 'Forbidden: You can only mark attendance for Course assignments.'], 403);
            }
        } else {
            return response()->json(['message' => 'Forbidden: Your role cannot mark attendance.'], 403);
        }

        if (!$assignment->section) {
            return response()->json(['message' => 'Assignment does not have a valid section for program type check.'], 422);
        }

        // Students do not store program_type_id directly in this schema; derive it from section.
        $assignmentProgramTypeId = $assignment->section->program_type_id;
        $studentProgramTypeId = $student->section?->program_type_id;

        if (is_null($studentProgramTypeId)) {
            return response()->json([
                'message' => 'Student does not have a section/program type assigned.'
            ], 422);
        }

        if ((int) $assignmentProgramTypeId !== (int) $studentProgramTypeId) {
            return response()->json([
                'message' => 'Program type mismatch: Student and Assignment section program types do not match.'
            ], 422);
        }

        // For course attendance, ensure student belongs to the same section as the assignment.
        if ($assignment->type === 'Course' && !is_null($assignment->section_id) && (int) $student->section_id !== (int) $assignment->section_id) {
            return response()->json([
                'message' => 'Section mismatch: Student is not in the assigned section for this course.'
            ], 422);
        }

        $attendance = Attendance::updateOrCreate(
            [
                'assignment_id' => $validated['assignment_id'],
                'student_id' => $validated['student_id'],
            ],
            [
                'status' => $validated['status'],
                'marked_by_user_id' => $user->id,
                'marked_at' => now(),
            ]
        );

        return response()->json(['message' => 'Attendance recorded', 'attendance' => $attendance], 200);
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

        if ($user->hasRole('super_admin')) {
            // All schedule types
        } elseif ($user->hasRole('gngnunet_office_admin') || $user->hasRole('gngnunet_office_coordinator')) {
            // No type filter
        } elseif ($user->hasRole('tmhrt_office_admin') || $user->hasRole('tmhrt_office_coordinator')) {
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'Course');
            });
        } elseif ($user->hasRole('mezmur_office_admin') || $user->hasRole('mezmur_office_coordinator')) {
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'MezmurTraining');
            });
        } elseif ($user->hasRole('teacher')) {
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'Course');
            });
        } else {
            return response()->json(['message' => 'Forbidden: Your role cannot view attendance.'], 403);
        }

        if ($assignmentId = $request->input('assignment_id')) {
            $query->where('assignment_id', $assignmentId);
        }

        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }

        if ($status = $request->input('status')) {
            $allowedStatuses = ['Present', 'Absent', 'Excused'];
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
}
