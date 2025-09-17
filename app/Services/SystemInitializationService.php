<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;

class SystemInitializationService
{
    /**
     * Admin roles that indicate the system has been initialized
     */
    private static array $adminRoles = [
        'super_admin',
        'mezmur_office_admin',
        'tmhrt_office_admin',
        'distance_admin',
        'gngnunet_office_admin',
        'young_tmhrt_admin'
    ];

    /**
     * Check if the system needs initialization
     */
    public static function needsInitialization(): bool
    {
        // Check if any users exist
        if (User::count() === 0) {
            return true;
        }

        // Check if any admin users exist
        $hasAdmin = User::whereHas('roles', function ($query) {
            $query->whereIn('name', self::$adminRoles);
        })->exists();

        return !$hasAdmin;
    }

    /**
     * Check if the system is initialized
     */
    public static function isInitialized(): bool
    {
        return !self::needsInitialization();
    }

    /**
     * Get initialization status with details
     */
    public static function getInitializationStatus(): array
    {
        $needsInit = self::needsInitialization();
        $userCount = User::count();
        
        $adminCount = 0;
        if (!$needsInit) {
            $adminCount = User::whereHas('roles', function ($query) {
                $query->whereIn('name', self::$adminRoles);
            })->count();
        }

        return [
            'needs_initialization' => $needsInit,
            'is_initialized' => !$needsInit,
            'user_count' => $userCount,
            'admin_count' => $adminCount,
            'admin_roles' => self::$adminRoles
        ];
    }

    /**
     * Get available roles for first admin registration
     */
    public static function getInitializationRoles(): array
    {
        return [
            'super_admin' => 'Super Administrator (Full Access)',
            'mezmur_office_admin' => 'Mezmur Office Administrator',
            'tmhrt_office_admin' => 'TMHRT Office Administrator',
            'distance_admin' => 'Distance Learning Administrator',
            'gngnunet_office_admin' => 'Gngnunet Office Administrator',
            'young_tmhrt_admin' => 'Young TMHRT Administrator'
        ];
    }

    /**
     * Ensure roles exist in the system
     */
    public static function ensureRolesExist(): void
    {
        foreach (self::$adminRoles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }
}
