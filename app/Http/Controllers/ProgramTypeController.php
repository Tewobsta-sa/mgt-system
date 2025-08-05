<?php

namespace App\Http\Controllers;

use App\Models\ProgramType;
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
}
