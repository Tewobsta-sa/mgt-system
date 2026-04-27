<?php

// app/Http/Controllers/GradeController.php
namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Student;
use App\Models\AssignmentCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

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

/**
 * GET /api/grades/import/template/{courseId}
 * Returns a CSV template with one row per (assessment) student pair the teacher can fill.
 * Header columns: student_id, student_name, <assessment.title> (one per assessment).
 */
public function importTemplate($courseId)
{
    $user = Auth::user();
    if (! $user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $course = Course::with(['assessments' => fn ($q) => $q->orderBy('id')])
        ->findOrFail($courseId);

    if ($user->hasRole('teacher')) {
        $teacherCourseIds = AssignmentCourse::where('teacher_id', $user->id)->pluck('course_id')->toArray();
        if (! in_array((int) $courseId, $teacherCourseIds, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    }

    $sectionIds = AssignmentCourse::where('course_id', $course->id)
        ->with('assignment:id,section_id')
        ->get()
        ->pluck('assignment.section_id')
        ->filter()
        ->unique()
        ->values()
        ->all();
    $students = Student::whereIn('section_id', $sectionIds)->orderBy('name')->get();

    $headers = ['student_id', 'student_name'];
    foreach ($course->assessments as $a) {
        $headers[] = $a->title . ' (max ' . $a->max_score . ')';
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headers, null, 'A1');

    $rowIndex = 2;
    foreach ($students as $s) {
        $row = [$s->student_id, $s->name];
        foreach ($course->assessments as $_a) {
            $row[] = '';
        }
        $sheet->fromArray($row, null, 'A' . $rowIndex);
        $rowIndex++;
    }

    $writer = new CsvWriter($spreadsheet);
    $writer->setUseBOM(true);

    $filename = 'grades_template_' . preg_replace('/\W+/', '_', strtolower($course->name)) . '.csv';

    return response()->streamDownload(function () use ($writer) {
        $writer->save('php://output');
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

/**
 * POST /api/grades/import/{courseId}
 * Body: file=<xlsx|csv>
 * Header row format: student_id, student_name, <assessment title 1>, <assessment title 2>, ...
 * Validates each cell against assessment.max_score; rolls back the entire import on errors.
 */
public function importExcel(Request $request, $courseId)
{
    $user = Auth::user();
    if (! $user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
    ]);

    $course = Course::with('assessments')->findOrFail($courseId);

    if ($user->hasRole('teacher')) {
        $teacherCourseIds = AssignmentCourse::where('teacher_id', $user->id)->pluck('course_id')->toArray();
        if (! in_array((int) $courseId, $teacherCourseIds, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    }

    try {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Could not read file: ' . $e->getMessage()], 422);
    }

    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    if (count($rows) < 2) {
        return response()->json(['message' => 'File is empty or missing data rows.'], 422);
    }

    $header = array_map(fn ($h) => trim((string) $h), $rows[0]);
    if (count($header) < 3 || strcasecmp($header[0], 'student_id') !== 0) {
        return response()->json([
            'message' => 'First column must be student_id and at least one assessment column is required.',
        ], 422);
    }

    // Map each header (>=3rd col) to an assessment by matching the title prefix.
    $assessmentByCol = [];
    for ($c = 2; $c < count($header); $c++) {
        $cellHeader = $header[$c];
        // Strip "(max NN)" suffix if present.
        $title = trim(preg_replace('/\s*\(max\s+[\d.]+\)\s*$/i', '', $cellHeader));
        $match = $course->assessments->first(fn ($a) => strcasecmp($a->title, $title) === 0);
        if (! $match) {
            return response()->json([
                'message' => "Column '{$cellHeader}' does not match any assessment of this course.",
            ], 422);
        }
        $assessmentByCol[$c] = $match;
    }

    $errors = [];
    $savedCount = 0;

    DB::beginTransaction();
    try {
        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $studentIdRaw = isset($row[0]) ? trim((string) $row[0]) : '';
            if ($studentIdRaw === '') {
                continue; // blank row
            }

            $student = Student::where('student_id', $studentIdRaw)->orWhere('id', $studentIdRaw)->first();
            if (! $student) {
                $errors[] = ['row' => $r + 1, 'message' => "Unknown student '{$studentIdRaw}'"];
                continue;
            }

            foreach ($assessmentByCol as $colIdx => $assessment) {
                $cell = $row[$colIdx] ?? null;
                if ($cell === null || $cell === '') {
                    continue; // skip empty cells
                }
                if (! is_numeric($cell)) {
                    $errors[] = ['row' => $r + 1, 'message' => "Score for '{$assessment->title}' must be numeric"];
                    continue;
                }
                $score = (float) $cell;
                if ($score < 0 || $score > (float) $assessment->max_score) {
                    $errors[] = [
                        'row' => $r + 1,
                        'message' => "Score {$score} for '{$assessment->title}' is out of range (0..{$assessment->max_score})",
                    ];
                    continue;
                }

                Grade::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                    ['score' => $score]
                );
                $savedCount++;
            }
        }

        if (! empty($errors)) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed due to validation errors. No grades were saved.',
                'saved_count' => 0,
                'errors' => $errors,
            ], 422);
        }

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Import failed', 'error' => $e->getMessage()], 500);
    }

    return response()->json([
        'message' => 'Grades imported successfully',
        'saved_count' => $savedCount,
    ], 201);
}

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

