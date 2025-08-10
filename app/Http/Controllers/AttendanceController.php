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
        $student = Student::findOrFail($validated['student_id']);

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

        if ($assignment->section->program_type_id !== $student->program_type_id) {
            return response()->json([
                'message' => 'Program type mismatch: Student and Assignment section program types do not match.'
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

        $query = Attendance::with(['student', 'assignment', 'markedBy']);

        if ($user->hasRole('gngunet_office_admin') || $user->hasRole('gngunet_office_coordinator')) {
        } elseif ($user->hasRole('tmhrt_office_admin') || $user->hasRole('tmhrt_office_coordinator')) {
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'Course');
            });
        } elseif ($user->hasRole('mezmur_office_admin') || $user->hasRole('mezmur_office_coordinator')) {
            $query->whereHas('assignment', function ($q) {
                $q->where('type', 'MezmurTraining');
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
