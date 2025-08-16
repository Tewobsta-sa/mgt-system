<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\ProgramType;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentPromotionController extends Controller
{
    public function promoteStudent($studentId)
    {
        $student = Student::with('programType', 'section')->findOrFail($studentId);
        return $this->promote([$student]);
    }

    public function promoteVerifiedStudents(Request $request)
    {
        $students = Student::with('programType', 'section')
            ->where('is_verified', true)
            ->get();

        return $this->promote($students);
    }

    private function promote($students)
    {
        $programTypes = ProgramType::with('sections')->get()->keyBy('name');

        return DB::transaction(function () use ($students, $programTypes) {
            $updated = [];

            foreach ($students as $student) {
                $currentProgram = $student->programType->name;

                if ($currentProgram === 'Young') {
                    $lastSection = $programTypes['Young']->sections()->orderBy('id', 'desc')->first()->id;

                    if ($student->section_id != $lastSection) {
                        $student->section_id = $this->getNextSectionId($student->section_id, $programTypes['Young']);
                    } else {
                        $student->program_type_id = $programTypes['Regular']->id;
                        $student->section_id = $programTypes['Regular']->sections()->orderBy('id')->first()->id;
                        $student->student_id = $this->generateStudentId('REG');
                    }

                } elseif ($currentProgram === 'Regular') {
                    $lastSection = $programTypes['Regular']->sections()->orderBy('id', 'desc')->first()->id;

                    if ($student->section_id != $lastSection) {
                        $student->section_id = $this->getNextSectionId($student->section_id, $programTypes['Regular']);
                    } else {
                        $student->section_id = null;
                        $student->status = 'Graduated';
                    }

                } elseif ($currentProgram === 'Distance') {
                    $student->program_type_id = $programTypes['Regular']->id;
                    $student->section_id = $programTypes['Regular']->sections()->orderBy('id')->first()->id;
                    $student->student_id = $this->generateStudentId('REG');
                }

                $student->save();
                $updated[] = $student;
            }

            return response()->json([
                'message' => count($updated) . ' student(s) promoted successfully',
                'students' => $updated
            ]);
        });
    }

    private function getNextSectionId($currentSectionId, $programType)
    {
        $sections = $programType->sections()->orderBy('id')->pluck('id')->toArray();
        $currentIndex = array_search($currentSectionId, $sections);

        if ($currentIndex !== false && isset($sections[$currentIndex + 1])) {
            return $sections[$currentIndex + 1];
        }

        return $currentSectionId;
    }

    private function generateStudentId(string $prefix, ?string $round = null): string
    {
        if ($prefix === 'DIS' && $round) {
            $count = Student::where('student_id', 'like', "{$prefix}/{$round}/%")->count() + 1;
            return "{$prefix}/{$round}/{$count}";
        }
        $count = Student::where('student_id', 'like', "{$prefix}/%")->count() + 1;
        return "{$prefix}/{$count}";
    }
}
