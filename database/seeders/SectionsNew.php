<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProgramType;
use App\Models\Section;

class SectionsNew extends Seeder {
    public function run(): void {
        $young    = ProgramType::where('name','Young')->firstOrFail();
        $regular  = ProgramType::where('name','Regular')->firstOrFail();
        $distance = ProgramType::where('name','Distance')->firstOrFail();

        // Updated progression
        $youngSections   = ['Y1','Y2','Y3','Y4','Y5','Y6'];      // 6 sections
        $regularSections = ['R1','R2','R3','R4','R5','R6'];      // 6 sections
        $distanceSections = ['D1'];                                // Distance remains 1 section (can expand if needed)

        // Create Young sections
        foreach ($youngSections as $i => $name) {
            Section::firstOrCreate(
                [
                    'program_type_id' => $young->id,
                    'order_no'        => $i + 1
                ],
                ['name' => $name]
            );
        }

        // Create Regular sections
        foreach ($regularSections as $i => $name) {
            Section::firstOrCreate(
                [
                    'program_type_id' => $regular->id,
                    'order_no'        => $i + 1
                ],
                ['name' => $name]
            );
        }

        // Create Distance sections
        foreach ($distanceSections as $i => $name) {
            Section::firstOrCreate(
                [
                    'program_type_id' => $distance->id,
                    'order_no'        => $i + 1
                ],
                ['name' => $name]
            );
        }
    }
}
