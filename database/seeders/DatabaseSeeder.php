<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         $this->call(RoleSeeder::class);
        // User::factory(10)->create();
        $roleId = DB::table('roles')->where('name', 'super_admin')->value('id');
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'), // Change to a secure password
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
