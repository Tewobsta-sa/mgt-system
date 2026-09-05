<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Section;
use App\Models\ProgramType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    // ------------------ INDEX with search & pagination ------------------

    public function indexPreKG(Request $request)
    {
        return $this->queryByType('PreKG', $request);
    }

    public function indexRegular(Request $request)
    {
        return $this->queryByType('Regular', $request);
    }

    public function indexYoung(Request $request)
    {
        // Support legacy Young calls by redirecting to Regular query
        return $this->queryByType('Regular', $request);
    }

    public function indexDistance(Request $request)
    {
        return $this->queryByType('Distance', $request);
    }

    public function indexAll(Request $request)
    {
        $search = $request->query('search');
        $classification = $request->query('classification');
        $status = $request->query('status');
        $sectionId = $request->query('section_id');
        $track = $request->query('track');

        $query = Student::with(['address', 'contacts', 'section.programType']);

        if ($track) {
            $query->whereHas('section.programType', function ($q) use ($track) {
                if (strcasecmp($track, 'Regular') === 0) {
                    $q->whereIn('name', ['Regular', 'Young']);
                } else {
                    $q->where('name', $track);
                }
            });
        }

        if ($classification && $classification !== 'all') {
            $query->where('classification', $classification);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($sectionId) {
            $query->where('section_id', $sectionId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate($request->query('per_page', 10));
    }

    protected function queryByType(string $type, Request $request)
    {
        $search = $request->query('search');
        $classification = $request->query('classification');
        $status = $request->query('status');

        $query = Student::with(['address', 'contacts', 'section.programType']);

        if (strcasecmp($type, 'PreKG') === 0) {
            $query->where(function ($q) {
                $q->whereHas('section.programType', fn($pt) => $pt->where('name', 'PreKG'))
                  ->orWhereHas('section', fn($s) => $s->where('name', 'like', '%pre%kg%'))
                  ->orWhere('classification', 'prekg');
            });
        } elseif (strcasecmp($type, 'Regular') === 0) {
            $query->where(function ($q) {
                $q->whereHas('section.programType', fn($pt) => $pt->whereIn('name', ['Regular', 'Young']))
                  ->where(function ($qq) {
                      $qq->whereDoesntHave('section', fn($s) => $s->where('name', 'like', '%pre%kg%'))
                        ->orWhereNull('section_id');
                  });
            });
        } elseif (strcasecmp($type, 'Distance') === 0) {
            $query->whereHas('section.programType', fn($pt) => $pt->where('name', 'Distance'));
        }

        if ($classification && $classification !== 'all') {
            $query->where('classification', $classification);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($sectionId = $request->query('section_id')) {
            $query->where('section_id', $sectionId);
        }

        if ($request->has('is_mezmur')) {
            $query->where('is_mezmur', $request->query('is_mezmur'));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate($request->query('per_page', 10));
    }

    // ------------------ SHOW ------------------

    public function showPreKG($id)
    {
        return $this->showStudent($id);
    }

    public function showRegular($id)
    {
        return $this->showStudent($id);
    }

    public function showYoung($id)
    {
        return $this->showStudent($id);
    }

    public function showDistance($id)
    {
        return $this->showStudent($id);
    }

    public function showStudent($id)
    {
        $student = Student::with(['address', 'contacts', 'section.programType'])->findOrFail($id);

        $courseStats = DB::table('attendances')
            ->join('assignments', 'attendances.assignment_id', '=', 'assignments.id')
            ->where('attendances.student_id', $id)
            ->where('assignments.type', 'Course')
            ->selectRaw('count(*) as total, sum(case when status in ("Present", "Excused") then 1 else 0 end) as attended')
            ->first();

        $mezmurStats = DB::table('attendances')
            ->join('assignments', 'attendances.assignment_id', '=', 'assignments.id')
            ->where('attendances.student_id', $id)
            ->where('assignments.type', 'MezmurTraining')
            ->selectRaw('count(*) as total, sum(case when status in ("Present", "Excused") then 1 else 0 end) as attended')
            ->first();

        $student->course_attendance_avg = $courseStats && $courseStats->total > 0 
            ? round(($courseStats->attended / $courseStats->total) * 100, 2) 
            : 0;

        $student->mezmur_attendance_avg = $mezmurStats && $mezmurStats->total > 0 
            ? round(($mezmurStats->attended / $mezmurStats->total) * 100, 2) 
            : 0;

        return response()->json($student);
    }

    // ------------------ STORE (Unified Registration) ------------------

    public function storeUnified(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'christian_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'sex' => 'required|in:Male,Female',
            'educational_level' => 'nullable|string|max:255',
            'grade_level' => 'nullable|string|max:50',
            'occupation_type' => 'nullable|in:student,working',
            'current_school' => 'nullable|string|max:255',
            'current_office' => 'nullable|string|max:255',
            'family_guardian_name' => 'nullable|string|max:255',
            'family_guardian_phone' => 'nullable|string|max:25',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:25',
            'phone_number' => 'nullable|string|max:25',
            'email_address' => 'nullable|email|max:255',
            'telegram_user_name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'subcity' => 'nullable|string|max:255',
            'woreda' => 'nullable|string|max:255',
            'kebele' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:255',
            'classification' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:new,regular,Active,Inactive',
            'section_id' => 'nullable|exists:sections,id',
            'track' => 'nullable|string',
            'picture' => 'nullable|image|max:10240',
            'birth_certificates.*' => 'nullable|file|max:15360',
            'educational_certificates.*' => 'nullable|file|max:15360',
        ]);

        $track = $request->input('track', 'Regular');
        $prefix = match (strtolower($track)) {
            'distance' => 'DIS',
            'prekg' => 'PKG',
            default => 'REG',
        };

        $studentId = $this->generateStudentId($prefix, $request->input('round'));

        return DB::transaction(function () use ($request, $studentId, $prefix) {
            // Handle picture upload
            $picturePath = null;
            if ($request->hasFile('picture')) {
                $picturePath = $request->file('picture')->store('students/pictures', 'public');
            }

            // Handle birth certificates (1 or more)
            $birthCertPaths = [];
            if ($request->hasFile('birth_certificates')) {
                $files = is_array($request->file('birth_certificates')) 
                    ? $request->file('birth_certificates') 
                    : [$request->file('birth_certificates')];
                foreach ($files as $file) {
                    $birthCertPaths[] = $file->store('students/birth_certificates', 'public');
                }
            }

            // Handle educational certificates (1 or more)
            $eduCertPaths = [];
            if ($request->hasFile('educational_certificates')) {
                $files = is_array($request->file('educational_certificates')) 
                    ? $request->file('educational_certificates') 
                    : [$request->file('educational_certificates')];
                foreach ($files as $file) {
                    $eduCertPaths[] = $file->store('students/educational_certificates', 'public');
                }
            }

            // Calculate age if birth_date provided
            $age = $request->input('age');
            if (!$age && $request->filled('birth_date')) {
                $age = \Carbon\Carbon::parse($request->input('birth_date'))->age;
            }

            // Resolve section if not provided directly
            $sectionId = $request->input('section_id');
            if (!$sectionId && $request->filled('section_name')) {
                $section = Section::where('name', $request->input('section_name'))->first();
                $sectionId = $section?->id;
            }

            // Create student
            $student = Student::create([
                'student_id' => $studentId,
                'name' => $request->input('name'),
                'christian_name' => $request->input('christian_name'),
                'birth_date' => $request->input('birth_date'),
                'sex' => $request->input('sex', 'Male'),
                'age' => $age,
                'educational_level' => $request->input('educational_level'),
                'grade_level' => $request->input('grade_level'),
                'occupation_type' => $request->input('occupation_type', 'student'),
                'current_school' => $request->input('current_school'),
                'current_office' => $request->input('current_office'),
                'family_guardian_name' => $request->input('family_guardian_name') ?? $request->input('parent_name'),
                'family_guardian_phone' => $request->input('family_guardian_phone') ?? $request->input('parent_phone_number'),
                'emergency_contact_name' => $request->input('emergency_contact_name') ?? $request->input('emergency_responder'),
                'emergency_contact_phone' => $request->input('emergency_contact_phone') ?? $request->input('emergency_responder_phone_number'),
                'phone_number' => $request->input('phone_number'),
                'email_address' => $request->input('email_address'),
                'telegram_user_name' => $request->input('telegram_user_name'),
                'classification' => $request->input('classification'),
                'status' => $request->input('status', 'new'),
                'section_id' => $sectionId,
                'picture' => $picturePath,
                'birth_certificates' => !empty($birthCertPaths) ? $birthCertPaths : null,
                'educational_certificates' => !empty($eduCertPaths) ? $eduCertPaths : null,
                'round' => $request->input('round'),
            ]);

            // Create Address
            $student->address()->create([
                'city' => $request->input('city', 'Addis Ababa'),
                'subcity' => $request->input('subcity') ?? '',
                'district' => $request->input('woreda') ?? $request->input('district') ?? '',
                'woreda' => $request->input('woreda') ?? $request->input('district') ?? '',
                'kebele' => $request->input('kebele'),
                'special_place' => $request->input('special_place'),
                'house_number' => $request->input('house_no') ?? $request->input('house_number'),
                'house_no' => $request->input('house_no') ?? $request->input('house_number'),
            ]);

            // Create Contacts
            if ($guardianName = $student->family_guardian_name) {
                $student->contacts()->create([
                    'name' => $guardianName,
                    'phone_number' => $student->family_guardian_phone ?? '',
                    'type' => 'Guardian',
                ]);
            }

            if ($emergName = $student->emergency_contact_name) {
                $student->contacts()->create([
                    'name' => $emergName,
                    'phone_number' => $student->emergency_contact_phone ?? '',
                    'type' => 'Emergency',
                ]);
            }

            return response()->json($student->load(['address', 'contacts', 'section.programType']), 201);
        });
    }

    public function storeRegular(Request $request)
    {
        return $this->storeUnified($request->merge(['track' => 'Regular']));
    }

    public function storeYoung(Request $request)
    {
        return $this->storeUnified($request->merge(['track' => 'Regular']));
    }

    public function storeDistance(Request $request)
    {
        return $this->storeUnified($request->merge(['track' => 'Distance']));
    }

    public function storePreKG(Request $request)
    {
        return $this->storeUnified($request->merge(['track' => 'PreKG', 'classification' => 'prekg']));
    }

    // ------------------ UPDATE ------------------

    public function updateUnified(Request $request, $id)
    {
        $student = Student::with(['address', 'contacts', 'section.programType'])->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'christian_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'sex' => 'sometimes|required|in:Male,Female',
            'educational_level' => 'nullable|string|max:255',
            'grade_level' => 'nullable|string|max:50',
            'occupation_type' => 'nullable|in:student,working',
            'current_school' => 'nullable|string|max:255',
            'current_office' => 'nullable|string|max:255',
            'family_guardian_name' => 'nullable|string|max:255',
            'family_guardian_phone' => 'nullable|string|max:25',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:25',
            'phone_number' => 'nullable|string|max:25',
            'email_address' => 'nullable|email|max:255',
            'telegram_user_name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'subcity' => 'nullable|string|max:255',
            'woreda' => 'nullable|string|max:255',
            'kebele' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:255',
            'classification' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:new,regular,Active,Inactive',
            'section_id' => 'nullable|exists:sections,id',
            'picture' => 'nullable|image|max:10240',
            'birth_certificates.*' => 'nullable|file|max:15360',
            'educational_certificates.*' => 'nullable|file|max:15360',
        ]);

        return DB::transaction(function () use ($request, $student) {
            // Handle picture upload
            if ($request->hasFile('picture')) {
                if ($student->picture) {
                    Storage::disk('public')->delete($student->picture);
                }
                $student->picture = $request->file('picture')->store('students/pictures', 'public');
            }

            // Handle birth certificates (1 or more)
            if ($request->hasFile('birth_certificates')) {
                $existing = $student->birth_certificates ?? [];
                $files = is_array($request->file('birth_certificates')) 
                    ? $request->file('birth_certificates') 
                    : [$request->file('birth_certificates')];
                foreach ($files as $file) {
                    $existing[] = $file->store('students/birth_certificates', 'public');
                }
                $student->birth_certificates = $existing;
            }

            // Handle educational certificates (1 or more)
            if ($request->hasFile('educational_certificates')) {
                $existing = $student->educational_certificates ?? [];
                $files = is_array($request->file('educational_certificates')) 
                    ? $request->file('educational_certificates') 
                    : [$request->file('educational_certificates')];
                foreach ($files as $file) {
                    $existing[] = $file->store('students/educational_certificates', 'public');
                }
                $student->educational_certificates = $existing;
            }

            // Fill basic attributes
            $fields = [
                'name', 'christian_name', 'birth_date', 'sex', 'educational_level',
                'grade_level', 'occupation_type', 'current_school', 'current_office',
                'family_guardian_name', 'family_guardian_phone',
                'emergency_contact_name', 'emergency_contact_phone',
                'phone_number', 'email_address', 'telegram_user_name',
                'classification', 'status', 'section_id', 'round'
            ];

            foreach ($fields as $f) {
                if ($request->has($f)) {
                    $student->$f = $request->input($f);
                }
            }

            // Aliases from older forms
            if ($request->has('parent_name') && !$request->has('family_guardian_name')) {
                $student->family_guardian_name = $request->input('parent_name');
            }
            if ($request->has('parent_phone_number') && !$request->has('family_guardian_phone')) {
                $student->family_guardian_phone = $request->input('parent_phone_number');
            }
            if ($request->has('emergency_responder') && !$request->has('emergency_contact_name')) {
                $student->emergency_contact_name = $request->input('emergency_responder');
            }
            if ($request->has('emergency_responder_phone_number') && !$request->has('emergency_contact_phone')) {
                $student->emergency_contact_phone = $request->input('emergency_responder_phone_number');
            }

            if ($request->filled('birth_date')) {
                $student->age = \Carbon\Carbon::parse($request->input('birth_date'))->age;
            } elseif ($request->has('age')) {
                $student->age = $request->input('age');
            }

            $student->save();

            // Update Address
            $addressData = [];
            if ($request->has('city')) $addressData['city'] = $request->input('city');
            if ($request->has('subcity')) $addressData['subcity'] = $request->input('subcity');
            if ($request->has('woreda')) {
                $addressData['woreda'] = $request->input('woreda');
                $addressData['district'] = $request->input('woreda');
            } elseif ($request->has('district')) {
                $addressData['district'] = $request->input('district');
                $addressData['woreda'] = $request->input('district');
            }
            if ($request->has('kebele')) $addressData['kebele'] = $request->input('kebele');
            if ($request->has('special_place')) $addressData['special_place'] = $request->input('special_place');
            if ($request->has('house_no')) {
                $addressData['house_no'] = $request->input('house_no');
                $addressData['house_number'] = $request->input('house_no');
            } elseif ($request->has('house_number')) {
                $addressData['house_number'] = $request->input('house_number');
                $addressData['house_no'] = $request->input('house_number');
            }

            if (!empty($addressData)) {
                $student->address()->updateOrCreate([], $addressData);
            }

            return response()->json($student->load(['address', 'contacts', 'section.programType']));
        });
    }

    public function updateRegular(Request $request, $id)
    {
        return $this->updateUnified($request, $id);
    }

    public function updateYoung(Request $request, $id)
    {
        return $this->updateUnified($request, $id);
    }

    public function updateDistance(Request $request, $id)
    {
        return $this->updateUnified($request, $id);
    }

    // ------------------ BULK ACTIONS ------------------

    /**
     * Bulk update status (e.g. from 'new' to 'regular')
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'status' => 'required|string|in:new,regular,Active,Inactive',
        ]);

        $count = Student::whereIn('id', $validated['student_ids'])
            ->update(['status' => $validated['status']]);

        return response()->json([
            'message' => "Successfully updated {$count} students to '{$validated['status']}'.",
            'count' => $count,
        ]);
    }

    /**
     * Bulk export student identification card data (photo, QR data, name, address, section)
     */
    public function getStudentsForIdCards(Request $request)
    {
        $query = Student::with(['address', 'section.programType']);

        if ($ids = $request->input('student_ids')) {
            $idArray = is_array($ids) ? $ids : explode(',', (string)$ids);
            $query->whereIn('id', $idArray);
        }

        if ($sectionId = $request->input('section_id')) {
            $query->where('section_id', $sectionId);
        }

        if ($classification = $request->input('classification')) {
            $query->where('classification', $classification);
        }

        if ($track = $request->input('track')) {
            $query->whereHas('section.programType', fn($pt) => $pt->where('name', $track));
        }

        $students = $query->orderBy('name')->get();

        $cardData = $students->map(function ($s) {
            $addr = $s->address;
            $addrParts = array_filter([
                $addr?->city ?: 'Addis Ababa',
                $addr?->subcity ? "Subcity: {$addr->subcity}" : null,
                ($addr?->woreda ?: $addr?->district) ? "Woreda: " . ($addr->woreda ?: $addr->district) : null,
                $addr?->kebele ? "Kebele: {$addr->kebele}" : null,
                ($addr?->house_no ?: $addr?->house_number) ? "House: " . ($addr->house_no ?: $addr->house_number) : null,
            ]);

            return [
                'id' => $s->id,
                'student_id' => $s->student_id,
                'name' => $s->name,
                'christian_name' => $s->christian_name,
                'picture_url' => $s->picture_url,
                'section_name' => $s->section?->name ?? 'Unassigned',
                'track' => $s->section?->programType?->name ?? 'Regular',
                'classification' => $s->classification ?? 'Regular',
                'grade_level' => $s->grade_level ?? $s->educational_level ?? 'N/A',
                'address_string' => implode(', ', $addrParts),
                'phone_number' => $s->phone_number,
                'qr_code_data' => json_encode([
                    'sid' => $s->student_id,
                    'name' => $s->name,
                    'sec' => $s->section?->name,
                    'track' => $s->section?->programType?->name,
                ]),
            ];
        });

        return response()->json($cardData);
    }

    // ------------------ DELETE ------------------

    public function destroyRegular($id)
    {
        return $this->destroyStudent($id);
    }

    public function destroyYoung($id)
    {
        return $this->destroyStudent($id);
    }

    public function destroyDistance($id)
    {
        return $this->destroyStudent($id);
    }

    public function destroyStudent($id)
    {
        $student = Student::findOrFail($id);
        if ($student->picture) {
            Storage::disk('public')->delete($student->picture);
        }
        $student->delete();
        return response()->json(['message' => 'Student deleted successfully.'], 200);
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

    // ------------------ MEZMUR ASSIGNMENT ------------------

    public function assignMezmur(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id'
        ]);

        DB::transaction(function () use ($request) {
            Student::whereIn('id', $request->student_ids)
                ->update(['is_mezmur' => true, 'is_mezmur_member' => true]);
        });

        return response()->json(['message' => 'Students assigned to Mezmur successfully.']);
    }

    public function unassignMezmur(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id'
        ]);

        DB::transaction(function () use ($request) {
            Student::whereIn('id', $request->student_ids)
                ->update(['is_mezmur' => false, 'is_mezmur_member' => false]);
        });

        return response()->json(['message' => 'Students removed from Mezmur successfully.']);
    }

    public function indexMezmur(Request $request)
    {
        $search = $request->query('search');

        $query = Student::with(['address', 'contacts', 'section.programType'])
            ->where(function ($q) {
                $q->where('is_mezmur', true)
                  ->orWhere('is_mezmur_member', true);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate(10);
    }
}
