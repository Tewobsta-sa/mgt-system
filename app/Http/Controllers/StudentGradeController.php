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
        'student' => $student->only(['id', 'name']),
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
}

