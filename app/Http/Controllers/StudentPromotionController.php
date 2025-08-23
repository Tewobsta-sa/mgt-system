<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Section;
use App\Models\ProgramType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentPromotionController extends Controller
{
    /**
     * ✅ Verify a single student
     */
    public function verifyStudent($studentId)
    {
        $student = Student::findOrFail($studentId);

        if ($student->is_verified) {
            return response()->json([
                'message' => 'Student is already verified.'
            ], 200);
        }

        $student->is_verified = true;
        $student->verified_by = Auth::id();
        $student->verified_at = now();
        $student->save();

        return response()->json([
            'message' => 'Student verified successfully',
            'student' => $student
        ]);
    }

    /**
     * ✅ Bulk verify students
     */
    public function bulkVerify(Request $request)
    {
        $request->validate([
            'student_ids'   => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        DB::transaction(function () use ($request) {
            Student::whereIn('id', $request->student_ids)
                ->update([
                    'is_verified' => true,
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'Selected students have been verified successfully.',
        ]);
    }

    /**
     * ✅ Promote only REGULAR program students
     */
    public function promoteRegular()
    {
        $students = Student::with('section.programType')
            ->whereHas('section.programType', fn($q) => $q->where('name', 'Regular'))
            ->where('is_verified', true)
            ->get();

        return $this->promote($students, 'Regular');
    }

    /**
     * ✅ Promote only YOUNG program students
     */
    public function promoteYoung()
    {
        $students = Student::with('section.programType')
            ->whereHas('section.programType', fn($q) => $q->where('name', 'Young'))
            ->where('is_verified', true)
            ->get();

        return $this->promote($students, 'Young');
    }

    /**
     * ✅ Promote only DISTANCE program students
     */
    public function promoteDistance()
    {
        $students = Student::with('section.programType')
            ->whereHas('section.programType', fn($q) => $q->where('name', 'Distance'))
            ->where('is_verified', true)
            ->get();

        return $this->promote($students, 'Distance');
    }

    /**
     * ✅ Core promotion logic
     */
    private function promote($students, string $programName)
{
    // Load all program types with their sections ordered
    $programTypes = ProgramType::with(['sections' => function ($q) {
        $q->orderBy('order_no');
    }])->get()->keyBy('name');

    return DB::transaction(function () use ($students, $programTypes, $programName) {
        $updated = [];

        foreach ($students as $student) {
            // Get the actual program from the student's section
            $studentProgram = $student->section->programType->name ?? null;

            // Safety check
            if ($studentProgram !== $programName) {
                continue;
            }

            $currentProgram = $programTypes[$studentProgram] ?? null;
            $currentSections = $currentProgram ? $currentProgram->sections : collect();
            $lastSectionId = optional($currentSections->last())->id;

            if ($studentProgram === 'Young') {
                if ($student->section_id !== $lastSectionId) {
                    $student->section_id = $this->getNextSectionIdByOrder($student->section_id, $currentSections);
                } else {
                    $firstRegular = $programTypes['Regular']->sections->first();
                    if ($firstRegular) {
                        $student->section_id = $firstRegular->id;
                        $student->student_id = $this->generateStudentId('REG');
                    }
                }

            } elseif ($studentProgram === 'Regular') {
                if ($student->section_id !== $lastSectionId) {
                    $student->section_id = $this->getNextSectionIdByOrder($student->section_id, $currentSections);
                } else {
                    $student->section_id = null;
                    $student->status = 'Graduated';
                }

            } elseif ($studentProgram === 'Distance') {
                $firstRegular = $programTypes['Regular']->sections->first();
                if ($firstRegular) {
                    $student->section_id = $firstRegular->id;
                    $student->student_id = $this->generateStudentId('REG');
                }
            }

            // Reset verification after promotion
            $student->is_verified = false;
            $student->save();
            $updated[] = $student;
        }

        return response()->json([
            'message' => count($updated) . " {$programName} student(s) promoted successfully",
            'students' => $updated
        ]);
    });
}


    private function getNextSectionIdByOrder($currentSectionId, $orderedSections)
    {
        $index = $orderedSections->search(fn($s) => $s->id === $currentSectionId);
        if ($index !== false && isset($orderedSections[$index + 1])) {
            return $orderedSections[$index + 1]->id;
        }
        return $currentSectionId;
    }

    private function generateStudentId(string $prefix, ?string $round = null): string
    {
        return retry(3, function () use ($prefix, $round) {
            if ($prefix === 'DIS' && $round) {
                $count = Student::where('student_id', 'like', "{$prefix}/{$round}/%")->count() + 1;
                $candidate = "{$prefix}/{$round}/{$count}";
            } else {
                $count = Student::where('student_id', 'like', "{$prefix}/%")->count() + 1;
                $candidate = "{$prefix}/{$count}";
            }

            if (Student::where('student_id', $candidate)->exists()) {
                throw new \RuntimeException('collision');
            }
            return $candidate;
        }, 50);
    }
}
