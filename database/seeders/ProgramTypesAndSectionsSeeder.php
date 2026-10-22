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
            ['program_type_id' => $prekg->id, 'name' => 'ቅድመ-ሕፃናት 1'],
            ['order_no' => 1]
        );
        Section::firstOrCreate(
            ['program_type_id' => $prekg->id, 'name' => 'ቅድመ-ሕፃናት 2'],
            ['order_no' => 2]
        );

        // 3. Sections for Regular (Htsanat 1-4, Maekelawyan 5-8, Wetatoch 9-12)
        // Htsanat (Grades 1-4)
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'አንደኛ ክፍል (ሕፃናት)'],
            ['order_no' => 1]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'ሁለተኛ ክፍል (ሕፃናት)'],
            ['order_no' => 2]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'ሦስተኛ ክፍል (ሕፃናት)'],
            ['order_no' => 3]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'አራተኛ ክፍል (ሕፃናት)'],
            ['order_no' => 4]
        );

        // Maekelawyan (Grades 5-8)
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'አምስተኛ ክፍል (ማዕከላውያን)'],
            ['order_no' => 5]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'ስድስተኛ ክፍል (ማዕከላውያን)'],
            ['order_no' => 6]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'ሰባተኛ ክፍል (ማዕከላውያን)'],
            ['order_no' => 7]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'ስምንተኛ ክፍል (ማዕከላውያን)'],
            ['order_no' => 8]
        );

        // Wetatoch (Grades 9-12)
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'ዘጠነኛ ክፍል (ወጣቶች)'],
            ['order_no' => 9]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'አሥረኛ ክፍል (ወጣቶች)'],
            ['order_no' => 10]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'አሥራ አንደኛ ክፍል (ወጣቶች)'],
            ['order_no' => 11]
        );
        Section::firstOrCreate(
            ['program_type_id' => $regular->id, 'name' => 'አሥራ ሁለተኛ ክፍል (ወጣቶች)'],
            ['order_no' => 12]
        );

        // 4. Section for Distance
        Section::firstOrCreate(
            ['program_type_id' => $distance->id, 'name' => 'የርቀት 1'],
            ['order_no' => 1]
        );
    }
}
