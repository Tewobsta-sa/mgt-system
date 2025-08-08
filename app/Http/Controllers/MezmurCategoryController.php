<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MezmurCategory;
use App\Models\MezmurCategoryType;
use Illuminate\Http\Request;

class MezmurCategoryController extends Controller
{
    // List categories with optional search and pagination
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = MezmurCategory::with('type');

        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        $categories = $query->paginate(10);
        return response()->json($categories);
    }

    // Store a new category (type inferred by name)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_type_name' => 'required|string|max:255'
        ]);

        // Find category type by its name
        $categoryType = MezmurCategoryType::where('name', $validated['category_type_name'])->first();

        if (!$categoryType) {
            return response()->json([
                'message' => 'Category type not found'
            ], 404);
        }

        $category = MezmurCategory::create([
            'name' => $validated['name'],
            'category_type_id' => $categoryType->id
        ]);

        return response()->json([
            'message' => 'Mezmur category created successfully',
            'data' => $category
        ], 201);
    }


    // Show a specific category
    public function show($id)
    {
        $category = MezmurCategory::with('type')->findOrFail($id);
        return response()->json($category);
    }

    // Update category (including inferring type from type_name)
    public function update(Request $request, $id)
    {
        $category = MezmurCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type_name' => 'sometimes|required|string|exists:mezmur_category_types,name',
        ]);

        if (isset($validated['type_name'])) {
            $type = MezmurCategoryType::where('name', $validated['type_name'])->first();
            $category->mezmur_category_type_id = $type->id;
        }

        if (isset($validated['name'])) {
            $category->name = $validated['name'];
        }

        $category->save();

        return response()->json([
            'message' => 'Mezmur Category updated successfully.',
            'data' => $category
        ]);
    }

    // Delete category
    public function destroy($id)
    {
        $category = MezmurCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Deleted successfully.']);
    }
}
