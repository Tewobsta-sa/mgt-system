<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Assignment;
use App\Models\AssignmentCourse;
use App\Models\User;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    // List all sections with their program types
    public function index()
    {
        return response()->json(Section::with('programType')->get());
    }

    // Create a new section
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

    // Show a specific section with program type
    public function show($id)
    {
        $section = Section::with('programType')->findOrFail($id);

        return response()->json($section);
    }

    // Update section info
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

    // Delete a section
    public function destroy($id)
    {
        $section = Section::findOrFail($id);
        $section->delete();

        return response()->json(['message' => 'Section deleted']);
    }

    // Get courses assigned to this section via assignments
    public function courses($id)
    {
        $section = Section::findOrFail($id);

        $assignmentIds = Assignment::where('section_id', $id)
            ->where('type', 'Course')
            ->pluck('id');

        $courses = \App\Models\Course::whereHas('assignmentCourses', function ($query) use ($assignmentIds) {
            $query->whereIn('assignment_id', $assignmentIds);
        })->get();

        return response()->json($courses);
    }

    // Get students directly linked to this section
    public function students($id)
    {
        $section = Section::with('students')->findOrFail($id);
        return response()->json($section->students);
    }

    // Get teachers assigned to this section via assignments
    public function teachers($id)
    {
        $section = Section::findOrFail($id);

        $assignmentIds = Assignment::where('section_id', $id)
            ->where('type', 'Course')
            ->pluck('id');

        $teacherIds = AssignmentCourse::whereIn('assignment_id', $assignmentIds)
            ->pluck('teacher_id')->unique();

        $teachers = User::whereIn('id', $teacherIds)->get();

        return response()->json($teachers);
    }

    // Assign a course + teacher to the section (creates an assignment + assignment_course)
    // public function assignCourse(Request $request, $id)
    // {
    //     $section = Section::findOrFail($id);

    //     $data = $request->validate([
    //         'course_id' => 'required|exists:courses,id',
    //         'teacher_id' => 'required|exists:users,id',
    //         'start_time' => 'required|date_format:H:i',
    //         'end_time' => 'required|date_format:H:i|after:start_time',
    //         'day_of_week' => 'nullable|integer|min:0|max:6',
    //         'scheduled_date' => 'nullable|date',
    //         'location' => 'nullable|string',
    //         'default_period_order' => 'nullable|integer',
    //     ]);

    //     $course = \App\Models\Course::findOrFail($data['course_id']);
    //     $teacher = User::findOrFail($data['teacher_id']);

    //     if ($section->program_type_id !== $course->program_type_id) {
    //         return response()->json([
    //             'message' => 'Section and course program types do not match.'
    //         ], 422);
    //     }

    //     if (!$teacher->hasRole('teacher')) {
    //         return response()->json([
    //             'message' => 'Assigned user must have the teacher role.'
    //         ], 422);
    //     }

    //     $assignment = Assignment::create([
    //         'type' => 'Course',
    //         'section_id' => $section->id,
    //         'user_id' => $teacher->id,
    //         'location' => $data['location'] ?? null,
    //         'day_of_week' => $data['day_of_week'] ?? null,
    //         'scheduled_date' => $data['scheduled_date'] ?? null,
    //         'start_time' => $data['start_time'],
    //         'end_time' => $data['end_time'],
    //         'active' => true,
    //     ]);

    //     AssignmentCourse::create([
    //         'assignment_id' => $assignment->id,
    //         'course_id' => $course->id,
    //         'teacher_id' => $teacher->id,
    //         'default_period_order' => $data['default_period_order'] ?? null,
    //     ]);

    //     return response()->json([
    //         'message' => 'Course assigned to section successfully.',
    //         'assignment' => $assignment->load('assignmentCourses')
    //     ], 201);
    // }
}
