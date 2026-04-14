<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function export(Request $request, $type)
    {
        switch ($type) {
            case 'students':
                return $this->exportStudents($request);
            case 'grades':
                return $this->exportGrades($request);
            case 'attendance':
                return $this->exportAttendance($request);
            default:
                return response()->json(['message' => 'Invalid report type'], 400);
        }
    }

    private function exportStudents(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_report.csv"',
        ];

        $students = Student::with('section')->where('program_type_id', 2)->get();

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Student ID', 'Section', 'Verified', 'Birth Date']);

            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->name,
                    $student->student_id,
                    $student->section->name ?? 'N/A',
                    $student->is_verified ? 'Yes' : 'No',
                    $student->birth_date
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    private function exportGrades(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="grades_report.csv"',
        ];

        $grades = Grade::with(['student', 'assessment.course'])->get();

        $callback = function () use ($grades) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student', 'Course', 'Assessment', 'Score', 'Total']);

            foreach ($grades as $grade) {
                fputcsv($file, [
                    $grade->student->name ?? 'Unknown',
                    $grade->assessment->course->name ?? 'Unknown',
                    $grade->assessment->name ?? 'Unknown',
                    $grade->score,
                    $grade->assessment->max_score ?? 100
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    private function exportAttendance(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ];

        $attendance = Attendance::with(['student', 'assignment'])->get();

        $callback = function () use ($attendance) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Student', 'Assignment', 'Status']);

            foreach ($attendance as $record) {
                fputcsv($file, [
                    $record->marked_at,
                    $record->student->name ?? 'Unknown',
                    $record->assignment->type ?? 'Unknown',
                    $record->status
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
