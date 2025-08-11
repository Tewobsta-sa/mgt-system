<?php

namespace App\Http\Controllers;

use App\Models\ProgramType;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;

class ProgramTypeController extends Controller
{
    public function index()
    {
        return response()->json(ProgramType::with('sections')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:program_types,name',
            'description' => 'nullable|string'
        ]);

        $programType = ProgramType::create($data);

        return response()->json([
            'message' => 'Program type created successfully',
            'data' => $programType
        ], 201);
    }

    public function show($id)
    {
        $programType = ProgramType::with('sections')->findOrFail($id);

        return response()->json($programType);
    }

    public function update(Request $request, $id)
    {
        $programType = ProgramType::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|unique:program_types,name,' . $id,
            'description' => 'nullable|string'
        ]);

        $programType->update($data);

        return response()->json([
            'message' => 'Program type updated successfully',
            'data' => $programType
        ]);
    }

    public function destroy($id)
    {
        $programType = ProgramType::findOrFail($id);
        $programType->delete();

        return response()->json(['message' => 'Program type deleted']);
    }

    // Get sections of a program type
    public function sections($id)
    {
        $programType = ProgramType::with('sections')->findOrFail($id);
        return response()->json($programType->sections);
    }

    // Get courses of a program type
    public function courses($id)
    {
        $programType = ProgramType::with('courses')->findOrFail($id);
        return response()->json($programType->courses);
    }

    // Get teachers of a program type (users with 'teacher' role related to program type)
    public function teachers($id)
    {
        $programType = ProgramType::findOrFail($id);

        $teachers = $programType->users()
            ->role('teacher')  // assuming Spatie roles package
            ->get();

        return response()->json($teachers);
    }

    // Get students of a program type (from separate Student model)
    public function students($id)
    {
        $programType = ProgramType::findOrFail($id);

        $sectionIds = $programType->sections()->pluck('id');

        return Student::whereIn('section_id', $sectionIds)->get();
    }


}
