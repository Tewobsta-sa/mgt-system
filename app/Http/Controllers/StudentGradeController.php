<?php

// app/Http/Controllers/StudentGradesController.php
namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Course;
use App\Models\Assessment;
use Illuminate\Http\Request;

class StudentGradeController extends Controller
{
    // GET /api/students/{id}/totals
    public function totals($studentId)
{
    $student = Student::findOrFail($studentId);

    // get student's section
    $sectionId = $student->section_id;

    // get all assignments for this section
    $assignmentIds = \App\Models\Assignment::where('section_id', $sectionId)
        ->where('type', 'Course')
        ->pluck('id');

    // get course ids from pivot table
    $courseIds = \App\Models\AssignmentCourse::whereIn(
        'assignment_id',
        $assignmentIds
    )->pluck('course_id');

    $courses = Course::whereIn('id', $courseIds)->get();

    $results = [];
    $sumCourseGrades = 0;
    $countCourses = 0;

    foreach ($courses as $course) {
        $assessments = $course->assessments;

        if ($assessments->isEmpty()) {
            continue;
        }

        $sumWeighted = 0;
        $sumWeights = 0;

        foreach ($assessments as $assessment) {
            $sumWeights += (float) $assessment->weight;

            $grade = $assessment->grades()
                ->where('student_id', $studentId)
                ->first();

            $rawScore = $grade ? (float) $grade->score : 0;

            if ($assessment->max_score > 0) {
                $sumWeighted +=
                    ($rawScore / $assessment->max_score)
                    * $assessment->weight;
            }
        }

        if ($sumWeights <= 0) {
            continue;
        }

        $coursePercentage = round(
            ($sumWeighted / $sumWeights) * 100,
            2
        );

        $results[] = [
            'course_id' => $course->id,
            'course_name' => $course->name,
            'course_percentage' => $coursePercentage,
            'course_weight_sum' => $sumWeights
        ];

        $sumCourseGrades += $coursePercentage;
        $countCourses++;
    }

    $overallAverage = $countCourses
        ? round($sumCourseGrades / $countCourses, 2)
        : 0;

    return response()->json([
        'student' => $student->only(['id', 'name', 'student_id', 'is_verified']),
        'courses' => $results,
        'overall_average' => $overallAverage
    ]);
}

    public function sectionRankings($sectionId)
{
    $students = Student::where('section_id', $sectionId)->get();

    if ($students->isEmpty()) {
        return response()->json([]);
    }

    $rankings = [];

    foreach ($students as $student) {
        $totals = $this->calculateStudentTotals(
            $student->id,
            $sectionId
        );

        $rankings[] = [
            'id' => $student->id,
            'name' => $student->name,
            'student_id' => $student->student_id,
            'overall_average' => $totals['overall_average']
        ];
    }

    usort($rankings, fn($a, $b) =>
        $b['overall_average'] <=> $a['overall_average']
    );

    return response()->json(array_slice($rankings, 0, 10));
}

    private function calculateStudentTotals($studentId, $sectionId)
{
    $courseIds = \App\Models\AssignmentCourse::whereIn(
        'assignment_id',
        \App\Models\Assignment::where('section_id', $sectionId)
            ->where('type', 'Course')
            ->pluck('id')
    )->pluck('course_id');

    $courses = Course::whereIn('id', $courseIds)->get();

    $sumCourseGrades = 0;
    $countCourses = 0;

    foreach ($courses as $course) {
        $assessments = $course->assessments;

        if ($assessments->isEmpty()) continue;

        $sumWeighted = 0;
        $sumWeights = 0;

        foreach ($assessments as $assessment) {
            $sumWeights += (float) $assessment->weight;

            $grade = $assessment->grades()
                ->where('student_id', $studentId)
                ->first();

            $rawScore = $grade ? (float) $grade->score : 0;

            if ($assessment->max_score > 0) {
                $sumWeighted +=
                    ($rawScore / $assessment->max_score)
                    * $assessment->weight;
            }
        }

        if ($sumWeights > 0) {
            $sumCourseGrades +=
                ($sumWeighted / $sumWeights) * 100;

            $countCourses++;
        }
    }

    return [
        'overall_average' => $countCourses
            ? round($sumCourseGrades / $countCourses, 2)
            : 0
    ];
}

    /**
     * Get complete report cards for all students in a section (used for bulk PDF generation)
     */
    public function sectionReportCards($sectionId)
    {
        $students = Student::notFlagged()
            ->with(['address', 'section.programType'])
            ->where('section_id', $sectionId)
            ->get();

        if ($students->isEmpty()) {
            return response()->json([]);
        }

        $assignmentIds = \App\Models\Assignment::where('section_id', $sectionId)
            ->where('type', 'Course')
            ->pluck('id');

        $courseIds = \App\Models\AssignmentCourse::whereIn('assignment_id', $assignmentIds)
            ->pluck('course_id');

        $courses = Course::with('assessments')->whereIn('id', $courseIds)->get();

        // Fallback to program type courses if assignment not explicitly mapped yet
        if ($courses->isEmpty()) {
            $section = \App\Models\Section::find($sectionId);
            if ($section && $section->program_type_id) {
                $courses = Course::with('assessments')
                    ->where('program_type_id', $section->program_type_id)
                    ->get();
            }
        }

        $allReports = [];

        foreach ($students as $student) {
            $studentCourses = [];
            $sumCoursePct = 0;
            $countCourses = 0;

            foreach ($courses as $course) {
                $assessments = $course->assessments;
                if ($assessments->isEmpty()) continue;

                $sumWeighted = 0;
                $sumWeights = 0;
                $assessmentItems = [];

                foreach ($assessments as $assessment) {
                    $weight = (float) $assessment->weight;
                    $sumWeights += $weight;

                    $grade = $assessment->grades()->where('student_id', $student->id)->first();
                    $rawScore = $grade ? (float)$grade->score : 0;
                    $maxScore = (float)$assessment->max_score > 0 ? (float)$assessment->max_score : 100;

                    if ($maxScore > 0) {
                        $sumWeighted += ($rawScore / $maxScore) * $weight;
                    }

                    $assessmentItems[] = [
                        'assessment_id' => $assessment->id,
                        'title' => $assessment->title,
                        'score' => $rawScore,
                        'max_score' => $maxScore,
                        'weight' => $weight,
                    ];
                }

                if ($sumWeights > 0) {
                    $coursePct = round(($sumWeighted / $sumWeights) * 100, 2);
                    $sumCoursePct += $coursePct;
                    $countCourses++;
                } else {
                    $coursePct = 0;
                }

                $studentCourses[] = [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'course_percentage' => $coursePct,
                    'assessments' => $assessmentItems,
                ];
            }

            $overallAvg = $countCourses > 0 ? round($sumCoursePct / $countCourses, 2) : 0;

            $allReports[] = [
                'id' => $student->id,
                'name' => $student->name,
                'christian_name' => $student->christian_name,
                'student_id' => $student->student_id,
                'picture_url' => $student->picture_url,
                'section_id' => $student->section_id,
                'section_name' => $student->section?->name ?? 'Unassigned',
                'track' => $student->section?->programType?->name ?? 'Regular',
                'classification' => $student->classification ?? 'Regular',
                'overall_average' => $overallAvg,
                'courses' => $studentCourses,
            ];
        }

        // Calculate rank
        usort($allReports, fn($a, $b) => $b['overall_average'] <=> $a['overall_average']);
        foreach ($allReports as $idx => &$rep) {
            $rep['rank'] = $idx + 1;
            $rep['total_students'] = count($allReports);
        }

        return response()->json($allReports);
    }
}

