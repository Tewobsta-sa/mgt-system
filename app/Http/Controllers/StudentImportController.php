<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    private const HEADERS = [
        'name',
        'christian_name',
        'birth_date',
        'sex',
        'education_level',
        'grade_level',
        'occupation_type',
        'curr_school_or_office',
        'family_guardian_name',
        'family_guardian_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'city',
        'subcity',
        'woreda',
        'kebele',
        'house_no',
        'classification',
        'round',
        'section_name',
        'status',
        'shift',
    ];

    private const FIELD_GUIDE = [
        '[MANDATORY]',
        '[OPTIONAL]',
        '[OPTIONAL YYYY-MM-DD]',
        '[MANDATORY Male/Female]',
        '[MANDATORY: elementary/highschool/diploma/degree/masters/phd]',
        '[OPTIONAL e.g. Grade 4]',
        '[MANDATORY: student/working]',
        '[OPTIONAL School or Company Name]',
        '[MANDATORY Guardian Name]',
        '[MANDATORY Guardian Phone]',
        '[MANDATORY Emergency Name]',
        '[MANDATORY Emergency Phone]',
        '[OPTIONAL default Addis Ababa]',
        '[MANDATORY Subcity]',
        '[MANDATORY Woreda]',
        '[OPTIONAL Kebele]',
        '[OPTIONAL House No]',
        '[OPTIONAL: prekg/htsanat/maekelawyan/wetatoch/distance]',
        '[MANDATORY for Distance (e.g. 1, 2, 2016), OPTIONAL for others]',
        '[MANDATORY Section Name]',
        '[OPTIONAL: new/regular (default: new)]',
        '[OPTIONAL: day/night (default: day)]',
    ];

    private const SAMPLE_ROW = [
        '# Example: Abebe Kebede',
        'Gebre Mikael',
        '2012-05-15',
        'Male',
        'elementary',
        'Grade 5',
        'student',
        'St. Mary Primary School',
        'Kebede Tadesse',
        '0911223344',
        'Almaz Tesfaye',
        '0922334455',
        'Addis Ababa',
        'Bole',
        'Woreda 03',
        'Kebele 05',
        '1234',
        'maekelawyan',
        '1',
        'Maekelawyan 5 (Grade 5)',
        'new',
        'day',
    ];

    public function template(?string $track = 'Regular')
    {
        $filename = "students-import-template.csv";

        return new StreamedResponse(function () {
            $out = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel opens properly
            fwrite($out, "\xEF\xBB\xBF");
            
            // Header Row (clean machine keys)
            fputcsv($out, self::HEADERS);

            // Row 2: Mandatory / Optional / format guidelines
            fputcsv($out, self::FIELD_GUIDE);

            // Row 3: Concrete example row
            fputcsv($out, self::SAMPLE_ROW);

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $rows = $this->readRows($file);

        if (empty($rows)) {
            return response()->json(['message' => 'Empty file'], 422);
        }

        $headerRow = array_shift($rows);
        $headerRow = array_map(
            fn ($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-'], '_', (string) $h)))),
            $headerRow
        );

        // Verify key columns
        $requiredKeys = ['name', 'sex', 'family_guardian_name', 'family_guardian_phone', 'subcity', 'woreda', 'section_name'];
        $missing = array_diff($requiredKeys, $headerRow);
        if (!empty($missing)) {
            return response()->json([
                'message' => 'Missing required column headers: ' . implode(', ', $missing),
                'expected_columns' => self::HEADERS,
            ], 422);
        }

        $created = [];
        $errors = [];
        $skippedDuplicates = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNumber = $i + 2;

                // Skip comment or guide rows
                $firstCell = isset($row[0]) ? trim((string)$row[0]) : '';
                if ($firstCell === '' || str_starts_with($firstCell, '#') || str_starts_with($firstCell, '[')) {
                    continue;
                }

                // Map data
                $payload = [];
                foreach ($headerRow as $idx => $key) {
                    if (empty($key)) continue;
                    $payload[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : null;
                    if ($payload[$key] === '') {
                        $payload[$key] = null;
                    }
                }

                // Validate row
                $validator = Validator::make($payload, [
                    'name' => ['required', 'string', 'max:255'],
                    'christian_name' => ['nullable', 'string', 'max:255'],
                    'birth_date' => ['nullable', 'date'],
                    'sex' => ['required', 'in:Male,Female,male,female'],
                    'education_level' => ['nullable', 'string', 'max:255'],
                    'grade_level' => ['nullable', 'string', 'max:50'],
                    'occupation_type' => ['nullable', 'string'],
                    'curr_school_or_office' => ['nullable', 'string', 'max:255'],
                    'family_guardian_name' => ['required', 'string', 'max:255'],
                    'family_guardian_phone' => ['required', 'string', 'max:25'],
                    'emergency_contact_name' => ['nullable', 'string', 'max:255'],
                    'emergency_contact_phone' => ['nullable', 'string', 'max:25'],
                    'subcity' => ['required', 'string', 'max:255'],
                    'woreda' => ['required', 'string', 'max:255'],
                    'section_name' => ['required', 'string', 'max:255'],
                ], [], [
                    'name' => 'name',
                    'christian_name' => 'Christian name',
                    'birth_date' => 'birth date',
                    'sex' => 'sex',
                    'education_level' => 'education level',
                    'grade_level' => 'grade level',
                    'occupation_type' => 'occupation type',
                    'curr_school_or_office' => 'school/office',
                    'family_guardian_name' => 'guardian name',
                    'family_guardian_phone' => 'guardian phone',
                    'emergency_contact_name' => 'emergency contact name',
                    'emergency_contact_phone' => 'emergency contact phone',
                    'subcity' => 'subcity',
                    'woreda' => 'woreda',
                    'section_name' => 'section name',
                ]);

                if ($validator->fails()) {
                    $errors[] = ['row' => $rowNumber, 'errors' => $validator->errors()->all()];
                    continue;
                }

                // Resolve section
                $sectionName = $payload['section_name'];
                $section = Section::where('name', $sectionName)
                    ->orWhere('name', 'like', "%{$sectionName}%")
                    ->first();

                if (!$section) {
                    $errors[] = ['row' => $rowNumber, 'errors' => ["Section '{$sectionName}' not found in system."]];
                    continue;
                }

                // Determine classification & prefix
                $classification = $payload['classification'] ?? null;
                $trackName = $section->programType?->name ?? 'Regular';
                $prefix = match (strtolower($trackName)) {
                    'distance' => 'DIS',
                    'prekg' => 'PKG',
                    default => 'REG',
                };

                $round = $payload['round'] ?? null;
                if ($prefix === 'DIS' && empty($round)) {
                    $errors[] = ['row' => $rowNumber, 'errors' => ["የየርቀት ትምህርት ተማሪዎች ዙር (Round) ማካተት ግዴታ ነው። (Round is mandatory for Distance students)."]];
                    continue;
                }

                // Duplicate-student detection: identical name + birth date, or
                // identical name + shared guardian/own phone -> skip the row.
                if ($this->isDuplicateRow($payload)) {
                    $skippedDuplicates[] = [
                        'row' => $rowNumber,
                        'name' => $payload['name'],
                        'reason' => 'Possible duplicate - a student with the same name and birth date/phone already exists.',
                    ];
                    continue;
                }

                $studentId = $this->generateStudentId($prefix, $round);

                $age = null;
                if (!empty($payload['birth_date'])) {
                    try {
                        $age = \Carbon\Carbon::parse($payload['birth_date'])->age;
                    } catch (\Throwable $t) {
                        $age = null;
                    }
                }

                $occupation = strtolower($payload['occupation_type'] ?? 'student');
                $currSchool = null;
                $currOffice = null;
                if (!empty($payload['curr_school_or_office'])) {
                    if ($occupation === 'working') {
                        $currOffice = $payload['curr_school_or_office'];
                    } else {
                        $currSchool = $payload['curr_school_or_office'];
                    }
                }

                $sex = ucfirst(strtolower($payload['sex']));

                $isNight = false;
                if (isset($payload['shift'])) {
                    $isNight = in_array(strtolower(trim($payload['shift'])), ['night', '1', 'true', 'yes', 'y']);
                } elseif (isset($payload['is_night'])) {
                    $isNight = filter_var($payload['is_night'], FILTER_VALIDATE_BOOLEAN);
                }

                // Create Student
                $student = Student::create([
                    'student_id' => $studentId,
                    'is_night' => $isNight,
                    'name' => $payload['name'],
                    'christian_name' => $payload['christian_name'] ?? null,
                    'birth_date' => $payload['birth_date'] ?? null,
                    'sex' => $sex,
                    'age' => $age,
                    'educational_level' => $payload['education_level'] ?? null,
                    'grade_level' => $payload['grade_level'] ?? null,
                    'occupation_type' => in_array($occupation, ['student', 'working']) ? $occupation : 'student',
                    'current_school' => $currSchool,
                    'current_office' => $currOffice,
                    'family_guardian_name' => $payload['family_guardian_name'],
                    'family_guardian_phone' => $payload['family_guardian_phone'],
                    'emergency_contact_name' => $payload['emergency_contact_name'] ?? $payload['family_guardian_name'],
                    'emergency_contact_phone' => $payload['emergency_contact_phone'] ?? $payload['family_guardian_phone'],
                    'phone_number' => $payload['family_guardian_phone'] ?? null,
                    'section_id' => $section->id,
                    'classification' => $classification,
                    'status' => strtolower($payload['status'] ?? 'new') === 'regular' ? 'regular' : 'new',
                ]);

                // Create Address
                $student->address()->create([
                    'city' => $payload['city'] ?? 'Addis Ababa',
                    'subcity' => $payload['subcity'],
                    'district' => $payload['woreda'],
                    'woreda' => $payload['woreda'],
                    'kebele' => $payload['kebele'] ?? null,
                    'house_number' => $payload['house_no'] ?? null,
                    'house_no' => $payload['house_no'] ?? null,
                ]);

                // Create Contacts
                $student->contacts()->create([
                    'name' => $student->family_guardian_name,
                    'phone_number' => $student->family_guardian_phone,
                    'type' => 'Guardian',
                ]);

                if (!empty($student->emergency_contact_name)) {
                    $student->contacts()->create([
                        'name' => $student->emergency_contact_name,
                        'phone_number' => $student->emergency_contact_phone,
                        'type' => 'Emergency',
                    ]);
                }

                $created[] = [
                    'row' => $rowNumber,
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                ];
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Import failed due to validation errors. No students were saved.',
                    'created_count' => 0,
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Students imported successfully',
            'created_count' => count($created),
            'created' => $created,
            'skipped_count' => count($skippedDuplicates),
            'skipped_duplicates' => $skippedDuplicates,
        ], 201);
    }

    /**
     * Duplicate check for an import row: same name + same birth date, or
     * same name + any shared phone number already registered.
     */
    private function isDuplicateRow(array $payload): bool
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') return false;

        $query = Student::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);

        $phones = array_filter(array_map(
            fn ($p) => preg_replace('/\D/', '', (string) $p),
            [
                $payload['family_guardian_phone'] ?? null,
                $payload['emergency_contact_phone'] ?? null,
            ]
        ));

        // Need at least one secondary field to confirm a duplicate.
        if (empty($payload['birth_date']) && count($phones) === 0) {
            return false;
        }

        $query->where(function ($q) use ($payload, $phones) {
            if (!empty($payload['birth_date'])) {
                $q->where('birth_date', $payload['birth_date']);
            }
            foreach ($phones as $phone) {
                if ($phone === '') continue;
                $q->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(family_guardian_phone,''),' ',''),'-',''),'+','') LIKE ?", ["%{$phone}%"])
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(phone_number,''),' ',''),'-',''),'+','') LIKE ?", ["%{$phone}%"]);
            }
        });

        return $query->exists();
    }

    private function generateStudentId(string $prefix, ?string $round = null): string
    {
        if ($prefix === 'DIS') {
            if (!$round) {
                throw new \InvalidArgumentException('Distance track requires a designated round (ዙር).');
            }
            $cleanRound = trim($round);
            $pattern = "{$prefix}/{$cleanRound}/";
            $existingIds = Student::where('student_id', 'like', "{$pattern}%")->pluck('student_id');

            $maxSeq = 0;
            foreach ($existingIds as $id) {
                $parts = explode('/', $id);
                $suffix = end($parts);
                if (is_numeric($suffix)) {
                    $num = (int) $suffix;
                    if ($num > $maxSeq) {
                        $maxSeq = $num;
                    }
                }
            }
            $nextSeq = $maxSeq + 1;
            $formattedId = sprintf("{$prefix}/{$cleanRound}/%03d", $nextSeq);

            while (Student::where('student_id', $formattedId)->exists()) {
                $nextSeq++;
                $formattedId = sprintf("{$prefix}/{$cleanRound}/%03d", $nextSeq);
            }

            return $formattedId;
        }

        $pattern = "{$prefix}/";
        $existingIds = Student::where('student_id', 'like', "{$pattern}%")->pluck('student_id');

        $maxSeq = 0;
        foreach ($existingIds as $id) {
            $parts = explode('/', $id);
            $suffix = end($parts);
            if (is_numeric($suffix)) {
                $num = (int) $suffix;
                if ($num > $maxSeq) {
                    $maxSeq = $num;
                }
            }
        }
        $nextSeq = $maxSeq + 1;
        $formattedId = sprintf("{$prefix}/%03d", $nextSeq);

        while (Student::where('student_id', $formattedId)->exists()) {
            $nextSeq++;
            $formattedId = sprintf("{$prefix}/%03d", $nextSeq);
        }

        return $formattedId;
    }

    private function readRows(\Illuminate\Http\UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->readCsv($file->getRealPath());
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->readSpreadsheet($file->getRealPath());
        }

        abort(422, 'Unsupported file type. Use CSV or XLSX.');
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (!$handle) return $rows;

        // Strip UTF-8 BOM
        $first = fgets($handle);
        if ($first !== false) {
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            rewind($handle);
            $content = stream_get_contents($handle);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            fclose($handle);

            $tmp = fopen('php://temp', 'r+');
            fwrite($tmp, $content);
            rewind($tmp);
            $handle = $tmp;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readSpreadsheet(string $path): array
    {
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            abort(422, 'XLSX support requires phpoffice/phpspreadsheet. Please install it or upload CSV.');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }
}
