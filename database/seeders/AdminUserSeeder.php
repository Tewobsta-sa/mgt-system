<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;  
use Spatie\Permission\Models\Role;  

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'security_question' => 'What is your favorite color?',
                'security_answer' => 'blue',
            ]
        );

        if (!$user->hasRole('super_admin')) {
            $user->assignRole($role);
        }
    }
}
