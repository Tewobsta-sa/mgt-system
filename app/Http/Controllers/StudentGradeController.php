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

        // get distinct course ids from student's assessments OR from course assignments
        $courseIds = Assessment::query()->distinct()->pluck('course_id');

        $courses = Course::whereIn('id', $courseIds)->get();

        $results = [];
        $sumCourseGrades = 0;
        $countCourses = 0;

        foreach ($courses as $course) {
            $assessments = $course->assessments;
            if ($assessments->isEmpty()) continue;

            $sumWeighted = 0;
            $sumWeights = 0;
            foreach ($assessments as $a) {
                $sumWeights += (float)$a->weight;
                $grade = $a->grades()->where('student_id', $studentId)->first();
                $rawScore = $grade ? (float)$grade->score : 0;
                if ($a->max_score > 0) {
                    $contribution = ($rawScore / (float)$a->max_score) * (float)$a->weight;
                } else {
                    $contribution = 0;
                }
                $sumWeighted += $contribution;
            }

            if ($sumWeights <= 0) continue;

            // final course percentage normalized to 100
            $coursePercentage = ($sumWeighted / $sumWeights) * 100;
            $coursePercentage = round($coursePercentage, 2);

            $results[] = [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'course_percentage' => $coursePercentage,
                'course_weight_sum' => $sumWeights
            ];

            $sumCourseGrades += $coursePercentage;
            $countCourses++;
        }

        $overallAverage = $countCourses ? round($sumCourseGrades / $countCourses, 2) : 0;

        return response()->json([
            'student' => $student->only(['id', 'name']),
            'courses' => $results,
            'overall_average' => $overallAverage
        ]);
    }
}

