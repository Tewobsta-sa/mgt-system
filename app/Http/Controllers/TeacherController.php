<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentCourse;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherController extends Controller
{
    /**
     * List courses taught by the currently authenticated teacher, grouped by
     * section so the teacher can pick which class's grades to open.
     *
     * Returns: [
     *   {
     *     course_id, course_name,
     *     section_id, section_name,
     *     program_type: { id, name },
     *     assignment_id
     *   }, ...
     * ]
     */
    public function myCourses(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $assignmentCourses = AssignmentCourse::with([
                'assignment.section.programType',
                'course.programType',
            ])
            ->where(function ($q) use ($user) {
                $q->where('teacher_id', $user->id);
            })
            ->orWhereHas('assignment', fn ($q) => $q->where('user_id', $user->id)->where('type', 'Course'))
            ->get();

        $result = $assignmentCourses->map(function (AssignmentCourse $ac) {
            if (! $ac->course || ! $ac->assignment || ! $ac->assignment->section) {
                return null;
            }
            return [
                'course_id'     => $ac->course->id,
                'course_name'   => $ac->course->name,
                'section_id'    => $ac->assignment->section->id,
                'section_name'  => $ac->assignment->section->name,
                'program_type'  => $ac->assignment->section->programType
                    ? [
                        'id'   => $ac->assignment->section->programType->id,
                        'name' => $ac->assignment->section->programType->name,
                    ]
                    : null,
                'assignment_id' => $ac->assignment_id,
            ];
        })->filter()->values();

        return response()->json($result);
    }

    /**
     * List students enrolled in a course, scoped to a section if the course is
     * taught in more than one section. Teachers may only see their own
     * sections; office admins may pass any course + section.
     */
    public function courseStudents(Request $request, int $courseId)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $course = Course::findOrFail($courseId);
        $sectionId = $request->input('section_id');

        // Sections where this course is actually assigned
        $sectionIds = Assignment::where('type', 'Course')
            ->whereHas('assignmentCourses', fn ($q) => $q->where('course_id', $course->id))
            ->pluck('section_id')
            ->unique();

        if ($user->hasRole('teacher')) {
            $teacherSectionIds = Assignment::where('type', 'Course')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereHas('assignmentCourses', fn ($qq) => $qq->where('teacher_id', $user->id)
                                                                      ->where('course_id', $course->id));
                })
                ->whereHas('assignmentCourses', fn ($q) => $q->where('course_id', $course->id))
                ->pluck('section_id')
                ->unique();
            $sectionIds = $sectionIds->intersect($teacherSectionIds);
        }

        if ($sectionId) {
            if (! $sectionIds->contains((int) $sectionId)) {
                return response()->json(['message' => 'Forbidden or section not part of this course'], 403);
            }
            $filterIds = [(int) $sectionId];
        } else {
            $filterIds = $sectionIds->values()->all();
        }

        $students = Student::with('section.programType')
            ->whereIn('section_id', $filterIds)
            ->orderBy('name')
            ->get();

        return response()->json([
            'course'   => $course->only(['id', 'name', 'credit_hour']),
            'students' => $students,
        ]);
    }
}
