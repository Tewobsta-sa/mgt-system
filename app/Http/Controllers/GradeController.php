<?php

// app/Http/Controllers/GradeController.php
namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    /**
     * POST /api/grades/bulk
     * Save many grades in one request. Accepts:
     *   { course_id?: int, grades: [ { assessment_id, student_id, score|null } ] }
     * Returns per-student course totals for convenience.
     */
    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'grades'                => 'required|array|min:1',
            'grades.*.assessment_id' => 'required|exists:assessments,id',
            'grades.*.student_id'    => 'required|exists:students,id',
            'grades.*.score'         => 'nullable|numeric|min:0',
        ]);

        $saved = [];
        $errors = [];

        DB::transaction(function () use ($data, &$saved, &$errors) {
            $assessmentCache = [];

            foreach ($data['grades'] as $i => $row) {
                $assessment = $assessmentCache[$row['assessment_id']]
                    ?? ($assessmentCache[$row['assessment_id']] = Assessment::find($row['assessment_id']));

                if (! $assessment) {
                    $errors[] = ['index' => $i, 'message' => 'Assessment not found'];
                    continue;
                }

                if ($row['score'] === null || $row['score'] === '') {
                    // Empty score => remove any existing grade (teacher clearing a cell)
                    Grade::where('assessment_id', $row['assessment_id'])
                        ->where('student_id', $row['student_id'])
                        ->delete();
                    continue;
                }

                if ((float) $row['score'] > (float) $assessment->max_score) {
                    $errors[] = [
                        'index'   => $i,
                        'message' => "Score {$row['score']} exceeds max {$assessment->max_score} for '{$assessment->title}'",
                    ];
                    continue;
                }

                $grade = Grade::updateOrCreate(
                    ['assessment_id' => $row['assessment_id'], 'student_id' => $row['student_id']],
                    ['score' => $row['score']]
                );

                $saved[] = $grade;
            }
        });

        if (! empty($errors)) {
            return response()->json([
                'message' => 'Some rows failed validation',
                'saved_count' => count($saved),
                'errors' => $errors,
            ], 422);
        }

        // Compute per-(student,course) totals for the affected rows
        $totals = [];
        $byPair = [];
        foreach ($saved as $g) {
            $courseId = Assessment::find($g->assessment_id)->course_id;
            $key = $g->student_id . ':' . $courseId;
            if (! isset($byPair[$key])) {
                $byPair[$key] = true;
                $t = $this->calculateCourseTotalsForStudent($g->student_id, $courseId);
                $totals[] = [
                    'student_id'        => $g->student_id,
                    'course_id'         => $courseId,
                    'course_total'      => $t['course_total'],
                    'course_percentage' => $t['course_percentage'],
                ];
            }
        }

        return response()->json([
            'saved_count' => count($saved),
            'totals'      => $totals,
        ], 201);
    }

    // POST /api/grades
    public function store(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    if (!($user->hasRole('tmhrt_office_admin') || $user->hasRole('teacher'))) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $data = $request->validate([
        'assessment_id' => 'required|exists:assessments,id',
        'student_id'    => 'required|exists:students,id',
        'score'         => 'required|numeric|min:0'
    ]);

    $assessment = Assessment::findOrFail($data['assessment_id']);

    // 🔒 Teacher can only grade own course
    if ($user->hasRole('teacher')) {
        $teacherCourseIds = \App\Models\AssignmentCourse::where('teacher_id', $user->id)
            ->pluck('course_id')
            ->toArray();

        if (!in_array($assessment->course_id, $teacherCourseIds)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    }

    if ($data['score'] > $assessment->max_score) {
        return response()->json([
            'message' => 'Score cannot be greater than assessment max_score'
        ], 422);
    }

    $grade = null;

    DB::transaction(function () use ($data, &$grade) {
        $grade = Grade::updateOrCreate(
            [
                'assessment_id' => $data['assessment_id'],
                'student_id' => $data['student_id']
            ],
            [
                'score' => $data['score']
            ]
        );
    });

    $courseTotals = $this->calculateCourseTotalsForStudent(
        $data['student_id'],
        $assessment->course_id
    );

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
        $user = Auth::user();
        if (!$user || !($user->hasRole('tmhrt_office_admin') || $user->hasRole('teacher'))) {
            return response()->json(['message' => 'Forbidden: You can only view grades with your role.'], 403);
        }

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
    $user = Auth::user();

    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $course = \App\Models\Course::with([
        'assessments.grades.student'
    ])->findOrFail($courseId);

    // 🔒 SECURITY: restrict teacher access
    if ($user->hasRole('teacher')) {
        $teacherCourseIds = \App\Models\AssignmentCourse::where('teacher_id', $user->id)
            ->pluck('course_id')
            ->toArray();

        if (!in_array($courseId, $teacherCourseIds)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    }

    $results = [];

    foreach ($course->assessments as $assessment) {
        $gradesData = [];

        foreach ($assessment->grades as $grade) {
            $gradesData[] = [
                'student_id' => $grade->student->id,
                'score' => $grade->score,
            ];
        }

        $results[] = [
            'assessment_id' => $assessment->id,
            'assessment_title' => $assessment->title ?? $assessment->name,
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

