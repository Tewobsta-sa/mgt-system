<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'name' => 'wereb_trainer',
                'description' => 'Gives wereb training',
            ],
            [
                'name' => 'mezmur_trainer',
                'description' => 'Gives mezmur training',
            ],
            [
                'name' => 'regular_teacher',
                'description' => 'Gives courses to regular students',
            ],
            [
                'name' => 'distance_teacher',
                'description' => 'Gives courses to distance students',
            ],
            [
                'name' => 'mezmur_office_admin',
                'description' => 'Registers mezmur and wereb trainers, plus mezmur coordinators; full authority on mezmur office tasks',
            ],
            [
                'name' => 'mezmur_office_coordinator',
                'description' => 'Handles mezmur office tasks except user registration',
            ],
            [
                'name' => 'tmhrt_office_admin',
                'description' => 'Registers regular teachers and tmhrt office coordinators; full authority on tmhrt office tasks',
            ],
            [
                'name' => 'tmhrt_office_coordinator',
                'description' => 'Full authority on tmhrt office tasks except user registration',
            ],
            [
                'name' => 'distance_admin',
                'description' => 'Registers distance teachers and distance coordinators',
            ],
            [
                'name' => 'distance_coordinator',
                'description' => 'Full authority on distance tasks except user registration',
            ],
            [
                'name' => 'gngnunet_office_admin',
                'description' => 'Registers students and gngnunet coordinators; full authority',
            ],
            [
                'name' => 'gngnunet_office_coordinator',
                'description' => 'Handles gngnunet office tasks except user registration',
            ],
            [
                'name' => 'super_admin',
                'description' => 'Registers admins and has access to everything',
            ],
        ];

        DB::table('roles')->insert($roles);
    }
}
