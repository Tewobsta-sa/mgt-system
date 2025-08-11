<?php

// app/Http/Controllers/AssessmentController.php
namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    // GET /api/assessments
    public function index(Request $request)
    {
        $query = Assessment::with('course');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('title', 'like', "%{$s}%")
                  ->orWhereHas('course', fn($q) => $q->where('name', 'like', "%{$s}%"));
        }

        $perPage = (int) $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $allowedSort = ['id','title','weight','max_score','created_at'];
        if (!in_array($sortBy, $allowedSort)) $sortBy = 'created_at';

        $assessments = $query->orderBy($sortBy, $sortDir)
                             ->paginate($perPage)
                             ->withQueryString();

        return response()->json($assessments);
    }

    // POST /api/assessments
    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required','exists:courses,id'],
            'title'     => ['required','string','max:255'],
            'max_score' => ['required','integer','min:1'],
            'weight'    => ['required','numeric','min:0','max:100'],
            'type'      => ['nullable','string','max:100']
        ]);

        $assessment = null;
        DB::transaction(function() use ($data, &$assessment) {
            $assessment = Assessment::create($data);
        });

        return response()->json($assessment, 201);
    }

    // GET /api/assessments/{id}
    public function show($id)
    {
        $assessment = Assessment::with(['course','grades.student'])->findOrFail($id);
        return response()->json($assessment);
    }

    // PUT/PATCH /api/assessments/{id}
    public function update(Request $request, $id)
    {
        $assessment = Assessment::findOrFail($id);

        $data = $request->validate([
            'course_id' => ['required','exists:courses,id'],
            'title'     => ['required','string','max:255'],
            'max_score' => ['required','integer','min:1'],
            'weight'    => ['required','numeric','min:0','max:100'],
            'type'      => ['nullable','string','max:100']
        ]);

        DB::transaction(function() use ($assessment, $data) {
            $assessment->update($data);
        });

        return response()->json($assessment);
    }

    // DELETE /api/assessments/{id}
    public function destroy($id)
    {
        $assessment = Assessment::findOrFail($id);
        $assessment->delete();
        return response()->json(null, 204);
    }
}

