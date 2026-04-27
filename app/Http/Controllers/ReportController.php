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
        return match ($type) {
            'students' => $this->exportStudents($request),
            'grades' => $this->exportGrades($request),
            'attendance' => $this->exportAttendance($request),
            default => response()->json(['message' => 'Invalid report type'], 400),
        };
    }

    /* -----------------------------------------
     * STUDENTS EXPORT (FIXED)
     * ----------------------------------------- */
    private function exportStudents(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_report.csv"',
        ];

        // FIX: removed program_type_id filter (doesn't exist)
        $students = Student::with(['section'])->get();

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Name',
                'Student ID',
                'Section',
                'Verified',
                'Birth Date'
            ]);

            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->name,
                    $student->student_id,
                    $student->section->name ?? 'N/A',
                    $student->is_verified ? 'Yes' : 'No',
                    $student->birth_date ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /* -----------------------------------------
     * GRADES EXPORT (FIXED SAFETY)
     * ----------------------------------------- */
    private function exportGrades(Request $request)
{
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="academic_report_cards.csv"',
    ];

    $students = Student::with([
        'grades.assessment.course'
    ])->get();

    $callback = function () use ($students) {
        $file = fopen('php://output', 'w');

        foreach ($students as $student) {

            fputcsv($file, []);
            fputcsv($file, [$student->name]);
            fputcsv($file, []); // spacing

            // GROUP GRADES BY COURSE
            $courses = [];

            foreach ($student->grades as $grade) {
                $course = $grade->assessment->course;

                if (!$course) continue;

                $courseId = $course->id;

                if (!isset($courses[$courseId])) {
                    $courses[$courseId] = [
                        'course_name' => $course->name,
                        'assessments' => [],
                        'total_weighted' => 0,
                        'total_max' => 0,
                    ];
                }

                $courses[$courseId]['assessments'][] = [
                    'title' => $grade->assessment->title,
                    'score' => $grade->score ?? 0,
                    'max' => $grade->assessment->max_score ?? 100,
                ];

                $courses[$courseId]['total_weighted'] += ($grade->score ?? 0);
                $courses[$courseId]['total_max'] += ($grade->assessment->max_score ?? 100);
            }

            foreach ($courses as $course) {

                // COURSE TITLE
                fputcsv($file, [$course['course_name']]);

                // HEADER ROW (dynamic)
                $header = array_map(
                    fn($a) => $a['title'],
                    $course['assessments']
                );
                $header[] = 'TOTAL';

                fputcsv($file, $header);

                // SCORE ROW
                $scores = array_map(
                    fn($a) => $a['score'],
                    $course['assessments']
                );

                $total = $course['total_max'] > 0
                    ? round(($course['total_weighted'] / $course['total_max']) * 100, 2)
                    : 0;

                $scores[] = $total . '%';

                fputcsv($file, $scores);

                fputcsv($file, []); // spacing between courses
            }
        }

        fclose($file);
    };

    return Response::stream($callback, 200, $headers);
}

    /* -----------------------------------------
     * ATTENDANCE EXPORT (SAFE)
     * ----------------------------------------- */
    private function exportAttendance(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ];

        $attendance = Attendance::with([
            'student',
            'assignment'
        ])->get();

        $callback = function () use ($attendance) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Amharic and other unicode correctly.
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Date',
                'Time',
                'Student',
                'Assignment',
                'Status',
            ]);

            foreach ($attendance as $record) {
                $markedAt = $record->marked_at
                    ? \Carbon\Carbon::parse($record->marked_at)
                    : null;

                fputcsv($file, [
                    $markedAt ? $markedAt->format('Y-m-d') : 'N/A',
                    $markedAt ? $markedAt->format('h:i A') : 'N/A',
                    $record->student->name ?? 'Unknown',
                    $record->assignment->type ?? 'Unknown',
                    $record->status ?? 'Unknown',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}