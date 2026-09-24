<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProgramType;
use App\Models\Section;

class ProgramTypesAndSectionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Canonical Program Tracks: PreKG, Regular, Distance
        $prekg = ProgramType::firstOrCreate(
            ['name' => 'PreKG'],
            ['description' => 'Pre-KG Early Childhood Program']
        );

        $regular = ProgramType::firstOrCreate(
            ['name' => 'Regular'],
            ['description' => 'Regular Sunday School Program']
        );

        $distance = ProgramType::firstOrCreate(
            ['name' => 'Distance'],
            ['description' => 'Distance Learning Program']
        );

        // 2. Sections for PreKG
        Section::firstOrCreate(
            ['program_type_id' => $prekg->id, 'name' => 'PreKG-1'],
            ['order_no' => 1]
        );
        Section::firstOrCreate(
            ['program_type_id' => $prekg->id, 'name' => 'PreKG-2'],
            ['order_no' => 2]
        );

        // 3. Sections for Regular (Htsanat 1-4, Maekelawyan 5-8, Wetatoch 9-12)
        // Htsanat (Grades 1-4)
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Htsanat 1 (Grade 1)'],
            ['order_no' => 1]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Htsanat 2 (Grade 2)'],
            ['order_no' => 2]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Htsanat 3 (Grade 3)'],
            ['order_no' => 3]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Htsanat 4 (Grade 4)'],
            ['order_no' => 4]
        );

        // Maekelawyan (Grades 5-8)
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Maekelawyan 5 (Grade 5)'],
            ['order_no' => 5]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Maekelawyan 6 (Grade 6)'],
            ['order_no' => 6]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Maekelawyan 7 (Grade 7)'],
            ['order_no' => 7]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Maekelawyan 8 (Grade 8)'],
            ['order_no' => 8]
        );

        // Wetatoch (Grades 9-12)
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Wetatoch 9 (Grade 9)'],
            ['order_no' => 9]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Wetatoch 10 (Grade 10)'],
            ['order_no' => 10]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Wetatoch 11 (Grade 11)'],
            ['order_no' => 11]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'Wetatoch 12 (Grade 12)'],
            ['order_no' => 12]
        );

        // 4. Section for Distance
        Section::firstOrCreate(
            ['program_type_id' => $distance->id, 'name' => 'Distance Section 1'],
            ['order_no' => 1]
        );
    }
}
