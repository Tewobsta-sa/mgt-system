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
    private const TRACK_REGULAR  = 'Regular';
    private const TRACK_YOUNG    = 'Young';
    private const TRACK_DISTANCE = 'Distance';

    private const HEADER_MAP = [
        self::TRACK_REGULAR => [
            'name', 'christian_name', 'age', 'educational_level',
            'subcity', 'district', 'special_place', 'house_number',
            'phone_number', 'emergency_responder', 'emergency_responder_phone_number',
            'section_name',
        ],
        self::TRACK_YOUNG => [
            'name', 'christian_name', 'age', 'educational_level',
            'subcity', 'district', 'special_place', 'house_number',
            'phone_number', 'parent_name', 'parent_phone_number',
            'section_name',
        ],
        self::TRACK_DISTANCE => [
            'name', 'christian_name', 'age', 'sex',
            'phone_number', 'email_address', 'telegram_user_name',
            'round', 'section_name',
        ],
    ];

    public function template(string $track)
    {
        $programType = $this->resolveProgramType($track);
        $headers = self::HEADER_MAP[$programType];

        $filename = "students-{$track}-import-template.csv";

        return new StreamedResponse(function () use ($headers) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 (Amharic) correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            // Hint row to help users (ignored on import; recognized by the starts-with-# rule)
            $example = array_fill(0, count($headers), '');
            fputcsv($out, $example);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function import(Request $request, string $track)
    {
        $programType = $this->resolveProgramType($track);

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
            fn ($h) => strtolower(trim(preg_replace('/\s+/', '_', (string) $h))),
            $headerRow
        );

        $expected = self::HEADER_MAP[$programType];
        $missing = array_diff($expected, $headerRow);
        if (! empty($missing)) {
            return response()->json([
                'message' => 'Missing required columns: ' . implode(', ', $missing),
                'expected_columns' => $expected,
            ], 422);
        }

        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNumber = $i + 2; // account for header

                // Skip fully empty rows
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $payload = [];
                foreach ($headerRow as $idx => $key) {
                    $payload[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : null;
                    if ($payload[$key] === '') {
                        $payload[$key] = null;
                    }
                }

                $validator = $this->validatorFor($programType, $payload);
                if ($validator->fails()) {
                    $errors[] = ['row' => $rowNumber, 'errors' => $validator->errors()->all()];
                    continue;
                }

                try {
                    $section = $this->resolveSection($payload['section_name'], $programType);
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $rowNumber, 'errors' => [$e->getMessage()]];
                    continue;
                }

                $studentId = $this->generateStudentId($programType, $payload['round'] ?? null);

                $studentAttrs = [
                    'student_id'        => $studentId,
                    'name'              => $payload['name'],
                    'christian_name'    => $payload['christian_name'] ?? null,
                    'age'               => isset($payload['age']) ? (int) $payload['age'] : null,
                    'phone_number'      => $payload['phone_number'] ?? null,
                    'educational_level' => $payload['educational_level'] ?? null,
                    'section_id'        => $section->id,
                ];

                if ($programType === self::TRACK_DISTANCE) {
                    $studentAttrs['sex'] = $payload['sex'] ?? null;
                    $studentAttrs['email_address'] = $payload['email_address'] ?? null;
                    $studentAttrs['telegram_user_name'] = $payload['telegram_user_name'] ?? null;
                    $studentAttrs['round'] = $payload['round'] ?? null;
                }

                $student = Student::create($studentAttrs);

                if (in_array($programType, [self::TRACK_REGULAR, self::TRACK_YOUNG], true)) {
                    $student->address()->create([
                        'subcity'       => $payload['subcity'] ?? null,
                        'district'      => $payload['district'] ?? null,
                        'special_place' => $payload['special_place'] ?? null,
                        'house_number'  => $payload['house_number'] ?? null,
                    ]);

                    if ($programType === self::TRACK_REGULAR) {
                        $student->contacts()->create([
                            'name'         => $payload['emergency_responder'],
                            'phone_number' => $payload['emergency_responder_phone_number'],
                            'relationship' => 'Emergency Responder',
                        ]);
                    } else {
                        $student->contacts()->create([
                            'name'         => $payload['parent_name'],
                            'phone_number' => $payload['parent_phone_number'],
                            'relationship' => 'Parent',
                        ]);
                    }
                }

                $created[] = [
                    'row'        => $rowNumber,
                    'student_id' => $student->student_id,
                    'name'       => $student->name,
                ];
            }

            if (! empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message'       => 'Import failed due to validation errors. No students were saved.',
                    'created_count' => 0,
                    'errors'        => $errors,
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message'       => 'Students imported successfully',
            'created_count' => count($created),
            'created'       => $created,
        ], 201);
    }

    private function validatorFor(string $programType, array $payload): \Illuminate\Validation\Validator
    {
        $rules = [
            'name'           => ['required', 'string', 'max:255'],
            'christian_name' => ['nullable', 'string', 'max:255'],
            'age'            => ['required', 'integer', 'min:1', 'max:120'],
            'phone_number'   => ['required', 'string', 'max:20'],
            'section_name'   => ['required', 'string', 'max:255'],
        ];

        if ($programType === self::TRACK_REGULAR || $programType === self::TRACK_YOUNG) {
            $rules['educational_level'] = ['required', 'string', 'max:255'];
            $rules['subcity']           = ['required', 'string', 'max:255'];
            $rules['district']          = ['required', 'string', 'max:255'];
            $rules['special_place']     = ['nullable', 'string', 'max:255'];
            $rules['house_number']      = ['nullable', 'string', 'max:255'];
        }

        if ($programType === self::TRACK_REGULAR) {
            $rules['emergency_responder']              = ['required', 'string', 'max:255'];
            $rules['emergency_responder_phone_number'] = ['required', 'string', 'max:20'];
        }

        if ($programType === self::TRACK_YOUNG) {
            $rules['parent_name']         = ['required', 'string', 'max:255'];
            $rules['parent_phone_number'] = ['required', 'string', 'max:20'];
        }

        if ($programType === self::TRACK_DISTANCE) {
            $rules['sex']                = ['required', 'in:Male,Female'];
            $rules['email_address']      = ['nullable', 'email', 'max:255'];
            $rules['telegram_user_name'] = ['nullable', 'string', 'max:255'];
            $rules['round']              = ['required', 'string', 'max:10'];
        }

        return Validator::make($payload, $rules);
    }

    private function resolveProgramType(string $track): string
    {
        $track = ucfirst(strtolower($track));
        if (! in_array($track, [self::TRACK_REGULAR, self::TRACK_YOUNG, self::TRACK_DISTANCE], true)) {
            abort(422, "Unknown track: {$track}");
        }
        return $track;
    }

    private function resolveSection(?string $sectionName, string $programType): Section
    {
        if (! $sectionName) {
            throw new \RuntimeException('Section not specified');
        }

        $section = Section::with('programType')->where('name', $sectionName)->first();

        if (! $section) {
            throw new \RuntimeException("Section '{$sectionName}' not found");
        }

        if (strcasecmp($section->programType->name ?? '', $programType) !== 0) {
            throw new \RuntimeException("Section '{$sectionName}' does not belong to program type {$programType}");
        }

        return $section;
    }

    private function generateStudentId(string $programType, ?string $round = null): string
    {
        $prefix = match ($programType) {
            self::TRACK_REGULAR  => 'REG',
            self::TRACK_YOUNG    => 'YNG',
            self::TRACK_DISTANCE => 'DIS',
        };

        if ($prefix === 'DIS' && $round) {
            $count = Student::where('student_id', 'like', "{$prefix}/{$round}/%")->count() + 1;
            return "{$prefix}/{$round}/{$count}";
        }

        $count = Student::where('student_id', 'like', "{$prefix}/%")->count() + 1;
        return "{$prefix}/{$count}";
    }

    /**
     * Read rows from a CSV or XLSX file. XLSX is supported when
     * phpoffice/phpspreadsheet is installed.
     *
     * @return array<int, array<int, mixed>>
     */
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
        if (! $handle) {
            return $rows;
        }

        // Strip UTF-8 BOM if present
        $first = fgets($handle);
        if ($first !== false) {
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            rewind($handle);
            // Re-read without BOM by writing to temp buffer
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
        if (! class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
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
