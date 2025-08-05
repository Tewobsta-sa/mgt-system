<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProgramType;

class ProgramTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programTypes = [
            ['name' => 'Regular'],
            ['name' => 'Young'],
            ['name' => 'Distance'],
        ];

        foreach ($programTypes as $type) {
            ProgramType::create($type);
        }
    }
}
