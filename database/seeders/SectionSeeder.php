<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\ProgramType;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            ['name' => 'Section A1', 'program_type' => 'Regular'],
            ['name' => 'Section A2', 'program_type' => 'Regular'],
            ['name' => 'Section Y1', 'program_type' => 'Young'],
            ['name' => 'Section Y2', 'program_type' => 'Young'],
            ['name' => 'Section D1', 'program_type' => 'Distance'],
            ['name' => 'Section D2', 'program_type' => 'Distance'],
        ];

        foreach ($sections as $section) {
            $programType = ProgramType::where('name', $section['program_type'])->first();

            if (!$programType) {
                $this->command->error("Program type '{$section['program_type']}' not found.");
                continue;
            }

            Section::create([
                'name' => $section['name'],
                'program_type_id' => $programType->id,
            ]);
        }
    }
}
