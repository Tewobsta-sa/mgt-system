<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Consolidate is_mezmur_member into is_mezmur, then drop column
        if (Schema::hasColumn('students', 'is_mezmur_member')) {
            DB::table('students')
                ->where('is_mezmur_member', true)
                ->update(['is_mezmur' => true]);

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('is_mezmur_member');
            });
        }

        // 2. Define the exact 5 canonical production roles
        $canonicalRoles = [
            'super_admin',
            'yesew_habt',  // የሰው ሀብት ክፍል (Absorbs gngnunet_kfl and related office admin roles)
            'tmhrt_kfl',   // የትምህርት ክፍል (Absorbs teacher, tmhrt office admin, etc.)
            'mezmur_kfl',  // የመዝሙር ክፍል (Absorbs mezmur office admin, etc.)
            'mereja_kfl',  // የመረጃ ክፍል
        ];

        foreach ($canonicalRoles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
        }

        // 3. Migrate users with legacy roles to the canonical 5 roles
        $roleMappings = [
            'gngnunet_kfl' => 'yesew_habt',
            'gngnunet_office_admin' => 'yesew_habt',
            'gngnunet_office_coordinator' => 'yesew_habt',
            'tmhrt_office_admin' => 'tmhrt_kfl',
            'tmhrt_office_coordinator' => 'tmhrt_kfl',
            'teacher' => 'tmhrt_kfl',
            'young_tmhrt_admin' => 'tmhrt_kfl',
            'young_gngnunet_admin' => 'yesew_habt',
            'distance_admin' => 'tmhrt_kfl',
            'distance_coordinator' => 'tmhrt_kfl',
            'mezmur_office_admin' => 'mezmur_kfl',
            'mezmur_office_coordinator' => 'mezmur_kfl',
        ];

        foreach ($roleMappings as $legacyRole => $targetRole) {
            $legacyRoleModels = Role::where('name', $legacyRole)->get();
            foreach ($legacyRoleModels as $rm) {
                try {
                    $users = User::role($legacyRole)->get();
                    foreach ($users as $user) {
                        $user->assignRole($targetRole);
                        $user->removeRole($legacyRole);
                    }
                } catch (\Exception $e) {}
                $rm->delete();
            }
        }

        // Clean up any remaining obsolete roles not in canonical list
        Role::whereNotIn('name', $canonicalRoles)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('students', 'is_mezmur_member')) {
            Schema::table('students', function (Blueprint $table) {
                $table->boolean('is_mezmur_member')->default(false)->after('is_mezmur');
            });
        }
    }
};
