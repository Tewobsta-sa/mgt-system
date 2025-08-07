<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Services\AssignmentService;

class AssignmentController extends Controller
{
    public function index()
    {
        return Assignment::with(['section.programType','user','mezmurs.category.type','courses.course','courses.teacher'])
            ->orderByDesc('id')->paginate(10);
    }

    public function store(StoreAssignmentRequest $request, AssignmentService $service)
    {
        $assignment = $service->create($request->validated());
        return response()->json($assignment, 201);
    }

    public function show($id)
    {
        $a = Assignment::with(['section.programType','user','mezmurs.category.type','courses.course','courses.teacher'])->findOrFail($id);
        return $a;
    }

    public function update(StoreAssignmentRequest $request, $id, AssignmentService $service)
    {
        // For brevity, reuse create() logic by deleting/overwriting relations,
        // or implement an explicit update method in the service.
        $existing = Assignment::findOrFail($id);
        $existing->delete(); // or set inactive, then recreate
        $assignment = $service->create($request->validated());
        return $assignment;
    }

    public function deactivate($id)
    {
        $a = Assignment::findOrFail($id);
        $a->active = false;
        $a->save();
        return response()->json(['message'=>'Assignment deactivated']);
    }
}

