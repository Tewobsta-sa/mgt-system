<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $config = config('role_permissions');

        foreach ($config as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if ($permissions === ['*']) continue;

            foreach ($permissions as $permName) {
                $permission = Permission::firstOrCreate(['name' => $permName]);
                $role->givePermissionTo($permission);
            }
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());
    }
}

