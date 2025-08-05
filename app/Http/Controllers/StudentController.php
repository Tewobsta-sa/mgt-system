<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    // ------------------ INDEX with search & pagination ------------------

    public function indexRegular(Request $request)
    {
        return $this->queryByType('Regular', $request);
    }

    public function indexYoung(Request $request)
    {
        return $this->queryByType('Young', $request);
    }

    public function indexDistance(Request $request)
    {
        return $this->queryByType('Distance', $request);
    }

    protected function queryByType(string $type, Request $request)
    {
        $search = $request->query('search');

        $query = Student::with(['address', 'contacts', 'section.programType'])
            ->whereHas('section.programType', fn($q) => $q->where('name', $type));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('student_id', 'like', "%$search%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate(10);
    }

    // ------------------ SHOW ------------------

    public function showRegular($id)
    {
        return $this->showByType($id, 'Regular');
    }

    public function showYoung($id)
    {
        return $this->showByType($id, 'Young');
    }

    public function showDistance($id)
    {
        return $this->showByType($id, 'Distance');
    }

    protected function showByType($id, string $type)
    {
        $student = Student::with(['address', 'contacts', 'section.programType'])->findOrFail($id);
        if (strcasecmp($student->section->programType->name, $type) !== 0) {
            return response()->json(['error' => 'Student type mismatch'], 422);
        }
        return $student;
    }

    // ------------------ STORE (Register) ------------------

    public function storeRegular(Request $request)
    {
        $section_id = $this->resolveSection($request->input('section_name'), 'Regular');
        return $this->registerRegularStudent($request, $section_id);
    }

    public function storeYoung(Request $request)
    {
        $section_id = $this->resolveSection($request->input('section_name'), 'Young');
        return $this->registerYoungStudent($request, $section_id);
    }

    public function storeDistance(Request $request)
    {
        $section_id = $this->resolveSection($request->input('section_name'), 'Distance');
        return $this->registerDistanceStudent($request, $section_id);
    }

    // Section name to ID + validation helper
    private function resolveSection(string $sectionName, string $programTypeName): int
    {
        $section = Section::with('programType')
            ->where('name', $sectionName)
            ->first();

        if (!$section) {
            abort(422, "Section not found");
        }

        if (strcasecmp($section->programType->name, $programTypeName) !== 0) {
            abort(422, "Section does not belong to program type $programTypeName");
        }

        return $section->id;
    }

    // Register Regular Student
    private function registerRegularStudent(Request $request, int $section_id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'christian_name' => 'required|string|max:255',
            'age' => 'required|integer|min:1',
            'educational_level' => 'required|string|max:255',
            'subcity' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'special_place' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:255',
            'emergency_responder' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'emergency_responder_phone_number' => 'required|string|max:20',
        ]);

        $studentId = $this->generateStudentId('REG');

        return DB::transaction(function () use ($request, $section_id, $studentId) {
            $student = Student::create([
                'student_id' => $studentId,
                'name' => $request->name,
                'christian_name' => $request->christian_name,
                'age' => $request->age,
                'educational_level' => $request->educational_level,
                'phone_number' => $request->phone_number,
                'section_id' => $section_id,
            ]);

            $student->address()->create($request->only('subcity', 'district', 'special_place', 'house_number'));

            $student->contacts()->create([
                'name' => $request->emergency_responder,
                'phone_number' => $request->emergency_responder_phone_number,
                'relationship' => 'Emergency Responder',
            ]);

            return response()->json($student->load('address', 'contacts'), 201);
        });
    }

    // Register Young Student
    private function registerYoungStudent(Request $request, int $section_id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'christian_name' => 'required|string|max:255',
            'age' => 'required|integer|min:1',
            'educational_level' => 'required|string|max:255',
            'subcity' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'special_place' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'parent_phone_number' => 'required|string|max:20',
        ]);

        $studentId = $this->generateStudentId('YNG');

        return DB::transaction(function () use ($request, $section_id, $studentId) {
            $student = Student::create([
                'student_id' => $studentId,
                'name' => $request->name,
                'christian_name' => $request->christian_name,
                'age' => $request->age,
                'educational_level' => $request->educational_level,
                'phone_number' => $request->phone_number,
                'section_id' => $section_id,
            ]);

            $student->address()->create($request->only('subcity', 'district', 'special_place', 'house_number'));

            $student->contacts()->create([
                'name' => $request->parent_name,
                'phone_number' => $request->parent_phone_number,
                'relationship' => 'Parent',
            ]);

            return response()->json($student->load('address', 'contacts'), 201);
        });
    }

    // Register Distance Student
    private function registerDistanceStudent(Request $request, int $section_id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'christian_name' => 'required|string|max:255',
            'age' => 'required|integer|min:1',
            'sex' => 'required|in:Male,Female',
            'telegram_user_name' => 'nullable|string|max:255',
            'email_address' => 'nullable|email|max:255',
            'phone_number' => 'required|string|max:20',
            'round' => 'required|string|max:10',
        ]);

        $studentId = $this->generateStudentId('DIS', $request->round);

        return DB::transaction(function () use ($request, $section_id, $studentId) {
            $student = Student::create([
                'student_id' => $studentId,
                'name' => $request->name,
                'christian_name' => $request->christian_name,
                'age' => $request->age,
                'sex' => $request->sex,
                'telegram_user_name' => $request->telegram_user_name,
                'email_address' => $request->email_address,
                'phone_number' => $request->phone_number,
                'section_id' => $section_id,
                'round' => $request->round,
            ]);

            return response()->json($student, 201);
        });
    }

    // ------------------ UPDATE ------------------

    public function updateRegular(Request $request, $id)
    {
        return $this->updateStudent($request, $id, 'Regular');
    }

    public function updateYoung(Request $request, $id)
    {
        return $this->updateStudent($request, $id, 'Young');
    }

    public function updateDistance(Request $request, $id)
    {
        return $this->updateStudent($request, $id, 'Distance');
    }

    private function updateStudent(Request $request, $id, string $type)
    {
        $student = Student::with('section.programType')->findOrFail($id);

        if (strcasecmp($student->section->programType->name, $type) !== 0) {
            return response()->json(['error' => 'Student type mismatch'], 422);
        }

        // Validation rules based on type
        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'christian_name' => 'sometimes|nullable|string|max:255',
            'age' => 'sometimes|required|integer|min:1',
            'phone_number' => 'sometimes|required|string|max:20',
            'section_name' => 'sometimes|required|string',
        ];

        if ($type === 'Regular' || $type === 'Young') {
            $rules = array_merge($rules, [
                'educational_level' => 'sometimes|required|string|max:255',
                'subcity' => 'sometimes|required|string|max:255',
                'district' => 'sometimes|required|string|max:255',
                'special_place' => 'nullable|string|max:255',
                'house_number' => 'nullable|string|max:255',
            ]);
        }

        if ($type === 'Regular') {
            $rules['emergency_responder'] = 'sometimes|required|string|max:255';
            $rules['emergency_responder_phone_number'] = 'sometimes|required|string|max:20';
        }

        if ($type === 'Young') {
            $rules['parent_name'] = 'sometimes|required|string|max:255';
            $rules['parent_phone_number'] = 'sometimes|required|string|max:20';
        }

        if ($type === 'Distance') {
            $rules = array_merge($rules, [
                'sex' => 'sometimes|required|in:Male,Female',
                'telegram_user_name' => 'nullable|string|max:255',
                'email_address' => 'nullable|email|max:255',
                'round' => 'sometimes|required|string|max:10',
            ]);
        }

        $request->validate($rules);

        // If section_name is provided, resolve and update section_id
        if ($request->has('section_name')) {
            $section_id = $this->resolveSection($request->input('section_name'), $type);
            $student->section_id = $section_id;
        }

        // Update basic fields
        foreach (['name', 'christian_name', 'age', 'phone_number', 'educational_level', 'sex', 'telegram_user_name', 'email_address', 'round'] as $field) {
            if ($request->has($field)) {
                $student->$field = $request->input($field);
            }
        }

        $student->save();

        // Update related address and contacts for Regular and Young types
        if ($type === 'Regular' || $type === 'Young') {
            $addressData = $request->only(['subcity', 'district', 'special_place', 'house_number']);
            if (!empty($addressData)) {
                $student->address()->updateOrCreate([], $addressData);
            }

            // Contacts update
            if ($type === 'Regular') {
                $student->contacts()->delete();
                $student->contacts()->create([
                    'name' => $request->input('emergency_responder'),
                    'phone_number' => $request->input('emergency_responder_phone_number'),
                    'relationship' => 'Emergency Responder',
                ]);
            }

            if ($type === 'Young') {
                $student->contacts()->delete();
                $student->contacts()->create([
                    'name' => $request->input('parent_name'),
                    'phone_number' => $request->input('parent_phone_number'),
                    'relationship' => 'Parent',
                ]);
            }
        }

        return response()->json($student->load('address', 'contacts'));
    }

    // ------------------ DELETE ------------------

    public function destroyRegular($id)
    {
        return $this->destroyByType($id, 'Regular');
    }

    public function destroyYoung($id)
    {
        return $this->destroyByType($id, 'Young');
    }

    public function destroyDistance($id)
    {
        return $this->destroyByType($id, 'Distance');
    }

    private function destroyByType($id, string $type)
    {
        $student = Student::with('section.programType')->findOrFail($id);
        if (strcasecmp($student->section->programType->name, $type) !== 0) {
            return response()->json(['error' => 'Student type mismatch'], 422);
        }
        $student->delete();
        return response()->json(null, 204);
    }

    // ------------------ STUDENT ID GENERATION ------------------

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
