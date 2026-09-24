<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // The 5 canonical system roles and their permissions from config
        $config = config('role_permissions');
        $validRoleNames = array_keys($config);

        foreach ($config as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($permissions === ['*']) continue;

            foreach ($permissions as $permName) {
                $permission = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                $role->givePermissionTo($permission);
            }
        }

        // Grant all permissions to super_admin
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Clean up any deprecated or non-canonical roles from database
        Role::whereNotIn('name', $validRoleNames)->delete();
    }
}
