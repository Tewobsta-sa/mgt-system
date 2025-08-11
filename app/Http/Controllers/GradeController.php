<?php

// app/Http/Controllers/GradeController.php
namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    // POST /api/grades
    public function store(Request $request)
    {
        $data = $request->validate([
            'assessment_id' => 'required|exists:assessments,id',
            'student_id'    => 'required|exists:students,id',
            'score'         => 'required|numeric|min:0'
        ]);

        $assessment = Assessment::findOrFail($data['assessment_id']);

        if ($data['score'] > $assessment->max_score) {
            return response()->json(['message' => 'Score cannot be greater than assessment max_score'], 422);
        }

        $grade = null;
        DB::transaction(function() use ($data, &$grade) {
            $grade = Grade::updateOrCreate(
                ['assessment_id' => $data['assessment_id'], 'student_id' => $data['student_id']],
                ['score' => $data['score']]
            );
        });

        // return grade + recalculated totals for convenience
        $courseTotals = $this->calculateCourseTotalsForStudent($data['student_id'], $assessment->course_id);

        return response()->json([
            'grade' => $grade,
            'course_total' => $courseTotals['course_total'],
            'course_percentage' => $courseTotals['course_percentage']
        ], 201);
    }

    // Optional: GET /api/grades?student_id= &assessment_id=
    public function index(Request $request)
    {
        $query = Grade::with(['assessment.course','student']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('assessment_id')) {
            $query->where('assessment_id', $request->assessment_id);
        }

        $perPage = (int) $request->input('per_page', 20);
        return response()->json($query->paginate($perPage));
    }

    // DELETE /api/grades/{id}
    public function destroy($id)
    {
        $grade = Grade::findOrFail($id);
        $grade->delete();
        return response()->json(null, 204);
    }

    /**
     * Calculate a student's total for a specific course.
     * Returns array: ['course_total' => numeric (sum of weighted contributions),
     *                 'course_percentage' => numeric (out of 100)]
     */
    protected function calculateCourseTotalsForStudent($studentId, $courseId)
    {
        $assessments = Assessment::where('course_id', $courseId)->get();

        $sumWeighted = 0;   // sum of weighted contributions e.g., 27 + 58.33 = 85.33
        $sumWeights = 0;    // sum of weights (normally should be 100 if setup correct)

        foreach ($assessments as $a) {
            $sumWeights += (float) $a->weight;
            $grade = $a->grades()->where('student_id', $studentId)->first();

            $rawScore = $grade ? (float) $grade->score : 0;

            // contribution = (rawScore / max_score) * weight
            if ($a->max_score > 0) {
                $contribution = ($rawScore / (float) $a->max_score) * (float) $a->weight;
            } else {
                $contribution = 0;
            }

            $sumWeighted += $contribution;
        }

        // course_total is the sumWeighted (already in % points) — 
        // if sumWeights != 100 you still get the correct weighted sum relative to configured weights.
        // course_percentage: normalize to out-of-100 if weights sum != 100
        $course_total = round($sumWeighted, 4);
        $course_percentage = ($sumWeights > 0) ? round(($sumWeighted / $sumWeights) * 100, 2) : 0;

        return [
            'course_total' => $course_total,
            'course_percentage' => $course_percentage
        ];
    }

    // Add this method inside your GradeController class

public function gradesForCourse($courseId)
{
    $course = \App\Models\Course::with(['assessments.grades.student'])->findOrFail($courseId);

    $results = [];

    foreach ($course->assessments as $assessment) {
        $gradesData = [];
        foreach ($assessment->grades as $grade) {
            $gradesData[] = [
                'student_id' => $grade->student->id,
                'student_name' => $grade->student->name,
                'score' => $grade->score,
            ];
        }

        $results[] = [
            'assessment_id' => $assessment->id,
            'assessment_title' => $assessment->title,
            'max_score' => $assessment->max_score,
            'weight' => $assessment->weight,
            'grades' => $gradesData,
        ];
    }

    return response()->json([
        'course_id' => $course->id,
        'course_name' => $course->name,
        'assessments' => $results,
    ]);
}

}

