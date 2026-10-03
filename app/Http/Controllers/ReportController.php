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
            'academic' => $this->exportAcademicStatus($request),
            default => response()->json(['message' => 'Invalid report type'], 400),
        };
    }

    /* -----------------------------------------
     * STUDENTS EXPORT (roster with section/status/track filters)
     * ----------------------------------------- */
    private function exportStudents(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="students_roster.csv"',
        ];

        $query = Student::with(['section.programType', 'address']);

        if ($sectionId = $request->input('section_id')) {
            $query->where('section_id', $sectionId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($track = $request->input('track')) {
            $query->whereHas('section.programType', fn ($q) => $q->where('name', $track));
        }
        if ($classification = $request->input('classification')) {
            $query->where('classification', $classification);
        }
        if ($request->has('is_night') && $request->input('is_night') !== '' && $request->input('is_night') !== 'all') {
            $query->where('is_night', filter_var($request->input('is_night'), FILTER_VALIDATE_BOOLEAN));
        }

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel displays Amharic characters correctly
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Student ID',
                'Full Name',
                'Christian Name',
                'Sex',
                'Birth Date',
                'Age',
                'Grade Level',
                'Educational Level',
                'Occupation Type',
                'Current School',
                'Current Office',
                'Section',
                'Track',
                'Classification',
                'Status',
                'Promotion Status',
                'Shift',
                'Mezmur Member',
                'Flagged',
                'Phone',
                'Emergency Contact Name',
                'Emergency Contact Phone',
                'Guardian Name',
                'Guardian Phone',
                'Subcity',
                'Woreda',
                'Registered At',
            ]);

            foreach ($query->orderBy('name')->cursor() as $student) {
                fputcsv($file, [
                    $student->student_id,
                    $student->name,
                    $student->christian_name ?? '',
                    $student->sex ?? '',
                    $student->birth_date ?? '',
                    $student->age ?? '',
                    $student->grade_level ?? '',
                    $student->educational_level ?? '',
                    $student->occupation_type ?? '',
                    $student->current_school ?? '',
                    $student->current_office ?? '',
                    $student->section->name ?? 'Unassigned',
                    $student->section->programType->name ?? '',
                    $student->classification ?? '',
                    $student->status ?? '',
                    $student->promotion_status ?? 'eligible',
                    $student->is_night ? 'Night' : 'Day',
                    $student->is_mezmur ? 'Yes' : 'No',
                    $student->is_flagged ? 'Yes' : 'No',
                    $student->phone_number ?? '',
                    $student->emergency_contact_name ?? '',
                    $student->emergency_contact_phone ?? '',
                    $student->family_guardian_name ?? '',
                    $student->family_guardian_phone ?? '',
                    $student->address->subcity ?? '',
                    $student->address->woreda ?? $student->address->district ?? '',
                    $student->created_at ? date('Y-m-d', strtotime($student->created_at)) : '',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /* -----------------------------------------
     * ACADEMIC STATUS EXPORT (per-student grade/attendance/promotion state)
     * ----------------------------------------- */
    private function exportAcademicStatus(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="academic_status_report.csv"',
        ];

        $query = Student::with(['section.programType', 'grades.assessment.course', 'attendances.assignment'])
            ->whereNotIn('status', ['Graduated', 'Inactive']);

        if ($sectionId = $request->input('section_id')) {
            $query->where('section_id', $sectionId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($track = $request->input('track')) {
            $query->whereHas('section.programType', fn ($q) => $q->where('name', $track));
        }
        if ($classification = $request->input('classification')) {
            $query->where('classification', $classification);
        }
        if ($request->has('is_night') && $request->input('is_night') !== '' && $request->input('is_night') !== 'all') {
            $query->where('is_night', filter_var($request->input('is_night'), FILTER_VALIDATE_BOOLEAN));
        }

        $students = $query->orderBy('name')->get();
        $courseMap = \App\Services\PromotionEvaluator::sectionCourseMap($students->pluck('section_id'));
        $thresholds = \App\Services\PromotionEvaluator::thresholds();

        $callback = function () use ($students, $courseMap, $thresholds) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Student ID',
                'Full Name',
                'Christian Name',
                'Section',
                'Track',
                'Status',
                'Promotion Status',
                'Overall Grade %',
                'Attendance %',
                "Eligible (Grade ≥ {$thresholds['min_grade']}%, Attendance ≥ {$thresholds['min_attendance']}%)",
                'Sessions Attended',
                'Sessions Total',
                'Flagged',
            ]);

            foreach ($students as $student) {
                $eval = \App\Services\PromotionEvaluator::evaluate($student, $courseMap);
                $summary = $eval['attendance_summary'];

                fputcsv($file, [
                    $student->student_id,
                    $student->name,
                    $student->christian_name ?? '',
                    $student->section->name ?? 'Unassigned',
                    $student->section->programType->name ?? '',
                    $student->status ?? '',
                    $student->promotion_status ?? 'eligible',
                    $eval['overall_grade_avg'] !== null ? $eval['overall_grade_avg'] . '%' : 'No results',
                    $eval['overall_attendance_avg'] !== null ? $eval['overall_attendance_avg'] . '%' : 'No records',
                    $eval['is_eligible'] ? 'Yes' : 'No',
                    $summary['present'],
                    $summary['total'],
                    $student->is_flagged ? 'Yes' : 'No',
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
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="academic_report_cards.csv"',
        ];

        $studentsQuery = Student::with('grades.assessment.course');
        if ($sectionId = $request->input('section_id')) {
            $studentsQuery->where('section_id', $sectionId);
        }

        $callback = function () use ($studentsQuery) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($studentsQuery->cursor() as $student) {

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
     * ATTENDANCE EXPORT (SAFE & COMPREHENSIVE)
     * ----------------------------------------- */
    private function exportAttendance(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ];

        $query = Attendance::with([
            'student.section.programType',
            'assignment.section.programType',
            'assignment.assignmentCourses.course',
            'assignment.mezmurs',
        ]);

        if ($sectionId = $request->input('section_id')) {
            $query->where(function ($q) use ($sectionId) {
                $q->whereHas('assignment', fn($qa) => $qa->where('section_id', $sectionId))
                  ->orWhereHas('student', fn($qs) => $qs->where('section_id', $sectionId));
            });
        }

        if ($type = $request->input('type')) {
            $query->whereHas('assignment', fn($q) => $q->where('type', $type));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->has('is_night') && $request->input('is_night') !== '' && $request->input('is_night') !== 'all') {
            $isNight = filter_var($request->input('is_night'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('assignment', fn ($q) => $q->where('is_night', $isNight));
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

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel displays Amharic characters correctly
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Date',
                'Student ID',
                'Full Name',
                'Christian Name',
                'Section',
                'Track / Classification',
                'Assignment Type',
                'Course / Topic',
                'Status'
            ]);

            foreach ($query->orderBy('marked_at', 'desc')->cursor() as $record) {
                $secName = $record->assignment?->section?->name ?? $record->student?->section?->name ?? 'Unassigned';
                $courseName = $record->assignment?->type === 'Course' 
                    ? ($record->assignment?->assignmentCourses?->first()?->course?->name ?? 'Course')
                    : ($record->assignment?->mezmurs?->first()?->title ?? 'Mezmur');

                fputcsv($file, [
                    $record->marked_at ?? 'N/A',
                    $record->student?->student_id ?? 'N/A',
                    $record->student?->name ?? 'Unknown',
                    $record->student?->christian_name ?? '',
                    $secName,
                    $record->student?->classification ?? $record->assignment?->section?->programType?->name ?? 'Regular',
                    $record->assignment?->type ?? 'N/A',
                    $courseName,
                    $record->status ?? 'Unknown'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}