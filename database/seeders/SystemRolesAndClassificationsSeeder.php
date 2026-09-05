<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\ProgramType;
use App\Models\Section;
use App\Models\User;

class SystemRolesAndClassificationsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure the 5 roles exist
        $roles = [
            'super_admin',
            'yesew_habt',
            'mereja_kfl',
            'mezmur_kfl',
            'tmhrt_kfl',
            'teacher', // keep generic instructor role
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Migrate any users with gngnunet_office_admin to yesew_habt
        $gngnunetUsers = User::whereHas('roles', fn($q) => $q->where('name', 'gngnunet_office_admin'))->get();
        foreach ($gngnunetUsers as $user) {
            $user->assignRole('yesew_habt');
        }

        // Also assign mezmur_kfl to mezmur_office_admin users if any
        $mezmurUsers = User::whereHas('roles', fn($q) => $q->where('name', 'mezmur_office_admin'))->get();
        foreach ($mezmurUsers as $user) {
            $user->assignRole('mezmur_kfl');
        }

        // Also assign tmhrt_kfl to tmhrt_office_admin users if any
        $tmhrtUsers = User::whereHas('roles', fn($q) => $q->where('name', 'tmhrt_office_admin'))->get();
        foreach ($tmhrtUsers as $user) {
            $user->assignRole('tmhrt_kfl');
        }

        // 2. Ensure Program Types: PreKG, Regular, Distance
        $prekg = ProgramType::firstOrCreate(['name' => 'PreKG'], ['description' => 'PreKG Early Childhood Program']);
        $regular = ProgramType::firstOrCreate(['name' => 'Regular'], ['description' => 'Regular Sunday School Program']);
        $distance = ProgramType::firstOrCreate(['name' => 'Distance'], ['description' => 'Distance Learning Program']);

        // 3. Ensure Sections for PreKG
        Section::firstOrCreate(['program_type_id' => $prekg->id, 'name' => 'PreKG-1'], ['order_no' => 1]);
        Section::firstOrCreate(['program_type_id' => $prekg->id, 'name' => 'PreKG-2'], ['order_no' => 2]);

        // 4. Ensure Sections for Regular (Htsanat 1-4, Maekelawyan 5-8, Wetatoch 9-12)
        // Htsanat
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Htsanat 1 (Grade 1)'], ['order_no' => 1]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Htsanat 2 (Grade 2)'], ['order_no' => 2]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Htsanat 3 (Grade 3)'], ['order_no' => 3]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Htsanat 4 (Grade 4)'], ['order_no' => 4]);

        // Maekelawyan
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Maekelawyan 5 (Grade 5)'], ['order_no' => 5]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Maekelawyan 6 (Grade 6)'], ['order_no' => 6]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Maekelawyan 7 (Grade 7)'], ['order_no' => 7]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Maekelawyan 8 (Grade 8)'], ['order_no' => 8]);

        // Wetatoch
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Wetatoch 9 (Grade 9)'], ['order_no' => 9]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Wetatoch 10 (Grade 10)'], ['order_no' => 10]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Wetatoch 11 (Grade 11)'], ['order_no' => 11]);
        Section::firstOrCreate(['program_type_id' => $regular->id, 'name' => 'Wetatoch 12 (Grade 12)'], ['order_no' => 12]);

        // 5. Ensure Distance section
        Section::firstOrCreate(['program_type_id' => $distance->id, 'name' => 'Distance-1'], ['order_no' => 1]);
    }
}
