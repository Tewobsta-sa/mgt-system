<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'wereb_trainer',
            'mezmur_trainer',
            'regular_teacher',
            'distance_teacher',
            'mezmur_office_admin',
            'mezmur_office_coordinator',
            'tmhrt_office_admin',
            'tmhrt_office_coordinator',
            'distance_admin',
            'distance_coordinator',
            'gngnunet_office_admin',
            'gngnunet_office_coordinator',
            'super_admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
