<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            // Make sure the role exists
            $role = Role::firstOrCreate(['name' => 'super_admin']);

            // Create user
            $user = User::create([
                'name' => 'Super Admin',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'security_question' => 'What is your favorite color?',
                'security_answer' => 'blue',
            ]);

            // Assign role using Spatie
            $user->assignRole($role);
        }
}
