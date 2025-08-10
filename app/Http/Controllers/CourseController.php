<?php 
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\ProgramType;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        return response()->json(Course::with('programType')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'credit_hour' => 'required|integer|min:1',
            'duration' => 'required|integer|min:1',
            'program_type_name' => 'required|string|exists:program_types,name',
        ]);

        // Find program type by name
        $programType = ProgramType::where('name', $validated['program_type_name'])->first();

        $course = Course::create([
            'name' => $validated['name'],
            'credit_hour' => $validated['credit_hour'],
            'duration' => $validated['duration'],
            'program_type_id' => $programType->id,
        ]);

        return response()->json($course, 201);
    }

    public function show(Course $course)
    {
        return response()->json($course->load('programType'));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'credit_hour' => 'sometimes|required|integer|min:1',
            'duration' => 'sometimes|required|integer|min:1',
            'program_type_name' => 'sometimes|required|string|exists:program_types,name',
        ]);

        if (isset($validated['program_type_name'])) {
            $programType = ProgramType::where('name', $validated['program_type_name'])->first();
            $validated['program_type_id'] = $programType->id;
            unset($validated['program_type_name']);
        }

        $course->update($validated);

        return response()->json($course);
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }
}
