<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\ProgramType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index()
    {
        return response()->json(Course::with(['programType', 'assessments'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'credit_hour' => 'required|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'semester' => 'nullable|string|max:100',
            'program_type_name' => 'required|string|exists:program_types,name',
            'assessments' => 'nullable|array',
            'assessments.*.title' => 'required_with:assessments|string|max:255',
            'assessments.*.max_score' => 'required_with:assessments|integer|min:1',
            'assessments.*.weight' => 'required_with:assessments|numeric|min:0|max:100',
            'assessments.*.type' => 'nullable|string|max:100',
        ]);

        $this->assertWeightsSumCorrect($validated['assessments'] ?? []);

        $programType = ProgramType::where('name', $validated['program_type_name'])->first();

        $course = DB::transaction(function () use ($validated, $programType) {
            $course = Course::create([
                'name' => $validated['name'],
                'credit_hour' => $validated['credit_hour'],
                'duration' => $validated['duration'] ?? 16,
                'semester' => $validated['semester'] ?? null,
                'program_type_id' => $programType->id,
            ]);

            foreach ($validated['assessments'] ?? [] as $a) {
                $course->assessments()->create([
                    'title' => $a['title'],
                    'max_score' => $a['max_score'],
                    'weight' => $a['weight'],
                    'type' => $a['type'] ?? null,
                ]);
            }

            return $course;
        });

        return response()->json($course->load(['programType', 'assessments']), 201);
    }

    public function show(Course $course)
    {
        return response()->json($course->load(['programType', 'assessments']));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'credit_hour' => 'sometimes|required|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'semester' => 'nullable|string|max:100',
            'program_type_name' => 'sometimes|required|string|exists:program_types,name',
            'assessments' => 'sometimes|nullable|array',
            'assessments.*.id' => 'nullable|integer',
            'assessments.*.title' => 'required_with:assessments|string|max:255',
            'assessments.*.max_score' => 'required_with:assessments|integer|min:1',
            'assessments.*.weight' => 'required_with:assessments|numeric|min:0|max:100',
            'assessments.*.type' => 'nullable|string|max:100',
        ]);

        if (isset($validated['assessments'])) {
            $this->assertWeightsSumCorrect($validated['assessments']);
        }

        $programType = null;
        if (isset($validated['program_type_name'])) {
            $programType = ProgramType::where('name', $validated['program_type_name'])->first();
        }

        DB::transaction(function () use ($validated, $course, $programType) {
            $fields = [];
            if (isset($validated['name'])) $fields['name'] = $validated['name'];
            if (isset($validated['credit_hour'])) $fields['credit_hour'] = $validated['credit_hour'];
            if (array_key_exists('duration', $validated)) $fields['duration'] = $validated['duration'] ?? 16;
            if (array_key_exists('semester', $validated)) $fields['semester'] = $validated['semester'];
            if ($programType) $fields['program_type_id'] = $programType->id;

            $course->update($fields);

            if (array_key_exists('assessments', $validated)) {
                $keepIds = [];
                foreach ($validated['assessments'] as $a) {
                    if (! empty($a['id'])) {
                        $existing = Assessment::where('course_id', $course->id)->find($a['id']);
                        if ($existing) {
                            $existing->update([
                                'title' => $a['title'],
                                'max_score' => $a['max_score'],
                                'weight' => $a['weight'],
                                'type' => $a['type'] ?? null,
                            ]);
                            $keepIds[] = $existing->id;
                        }
                    } else {
                        $created = $course->assessments()->create([
                            'title' => $a['title'],
                            'max_score' => $a['max_score'],
                            'weight' => $a['weight'],
                            'type' => $a['type'] ?? null,
                        ]);
                        $keepIds[] = $created->id;
                    }
                }
                Assessment::where('course_id', $course->id)
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            }
        });

        return response()->json($course->fresh()->load(['programType', 'assessments']));
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }

    public function assessments(Course $course)
    {
        return response()->json($course->assessments()->orderBy('id')->get());
    }

    private function assertWeightsSumCorrect(array $assessments): void
    {
        if (empty($assessments)) {
            return;
        }
        $sum = 0.0;
        foreach ($assessments as $a) {
            $sum += (float) ($a['weight'] ?? 0);
        }
        // Allow a tiny floating-point tolerance
        if (abs($sum - 100.0) > 0.01) {
            abort(response()->json([
                'message' => "Assessment weights must sum to 100. Current sum: {$sum}",
            ], 422));
        }
    }
}
