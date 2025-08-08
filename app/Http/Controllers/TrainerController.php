<?php

namespace App\Http\Controllers;

use App\Models\Trainer;
use App\Models\MezmurCategoryType;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    // List with search & pagination
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = Trainer::with('specialties');

        if ($search) {
            $query->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
        }

        $trainers = $query->paginate(10);

        return response()->json($trainers);
    }

    // Store new trainer with specialties
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|unique:trainers',
            'email' => 'nullable|email|unique:trainers',
            'gender' => 'required|in:male,female',
            'category_types' => 'array', 
            'category_types.*' => 'string', 
            'new_category_type' => 'nullable|string', 
        ]);

        // Create trainer
        $trainer = Trainer::create($request->only('name', 'phone', 'email', 'gender'));

        $categoryIds = [];

        // Attach existing or new category types by name
        if ($request->filled('category_types')) {
            foreach ($request->category_types as $catName) {
                $cat = MezmurCategoryType::firstOrCreate(['name' => $catName]);
                $categoryIds[] = $cat->id;
            }
        }

        // If new_category_type provided, create and add to list
        if ($request->filled('new_category_type')) {
            $newCat = MezmurCategoryType::firstOrCreate(['name' => $request->new_category_type]);
            $categoryIds[] = $newCat->id;
        }

        if (count($categoryIds) > 0) {
            $trainer->specialties()->sync($categoryIds);
        }

        return response()->json($trainer->load('specialties'), 201);
    }

    // Show single trainer
    public function show($id)
    {
        $trainer = Trainer::with('specialties')->findOrFail($id);
        return response()->json($trainer);
    }

    // Update trainer and specialties
    public function update(Request $request, $id)
    {
        $trainer = Trainer::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string',
            'phone' => 'nullable|unique:trainers,phone,' . $id,
            'email' => 'nullable|email|unique:trainers,email,' . $id,
            'gender' => 'sometimes|required|in:male,female',
            'category_types' => 'array',
            'category_types.*' => 'string',
            'new_category_type' => 'nullable|string',
        ]);

        $trainer->update($request->only('name', 'phone', 'email', 'gender'));

        $categoryIds = [];

        if ($request->filled('category_types')) {
            foreach ($request->category_types as $catName) {
                $cat = MezmurCategoryType::firstOrCreate(['name' => $catName]);
                $categoryIds[] = $cat->id;
            }
        }

        if ($request->filled('new_category_type')) {
            $newCat = MezmurCategoryType::firstOrCreate(['name' => $request->new_category_type]);
            $categoryIds[] = $newCat->id;
        }

        $trainer->specialties()->sync($categoryIds);

        return response()->json($trainer->load('specialties'));
    }

    // Delete trainer
    public function destroy($id)
    {
        $trainer = Trainer::findOrFail($id);
        $trainer->delete();

        return response()->json(['message' => 'Trainer deleted']);
    }
}


