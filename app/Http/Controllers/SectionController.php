<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index()
    {
        return response()->json(Section::with('programType')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'program_type_id' => 'required|exists:program_types,id'
        ]);

        $section = Section::create($data);

        return response()->json([
            'message' => 'Section created successfully',
            'data' => $section
        ], 201);
    }

    public function show($id)
    {
        $section = Section::with('programType')->findOrFail($id);

        return response()->json($section);
    }

    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'program_type_id' => 'sometimes|required|exists:program_types,id'
        ]);

        $section->update($data);

        return response()->json([
            'message' => 'Section updated successfully',
            'data' => $section
        ]);
    }

    public function destroy($id)
    {
        $section = Section::findOrFail($id);
        $section->delete();

        return response()->json(['message' => 'Section deleted']);
    }
}

