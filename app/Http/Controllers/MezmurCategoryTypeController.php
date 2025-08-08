<?php

namespace App\Http\Controllers;

use App\Models\MezmurCategoryType;
use Illuminate\Http\Request;

class MezmurCategoryTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = MezmurCategoryType::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:mezmur_category_types',
            'description' => 'nullable|string',
        ]);

        $type = MezmurCategoryType::create($validated);

        return response()->json($type, 201);
    }

    public function show($id)
    {
        return response()->json(MezmurCategoryType::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $type = MezmurCategoryType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:mezmur_category_types,name,' . $type->id,
            'description' => 'nullable|string',
        ]);

        $type->update($validated);

        return response()->json($type);
    }

    public function destroy($id)
    {
        $type = MezmurCategoryType::findOrFail($id);
        $type->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

