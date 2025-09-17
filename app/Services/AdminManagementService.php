<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminManagementService
{
    /**
     * Check if a user can delete another user
     */
    public static function canDeleteUser(User $currentUser, int $targetUserId): array
    {
        // Check if user is trying to delete themselves
        if ($currentUser->id === $targetUserId) {
            return [
                'can_delete' => false,
                'reason' => 'self_deletion',
                'message' => 'You cannot delete your own account. Please ask another super admin to delete your account.'
            ];
        }

        // Check if target user exists
        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return [
                'can_delete' => false,
                'reason' => 'user_not_found',
                'message' => 'User not found.'
            ];
        }

        // Check if target user is a super admin
        if ($targetUser->hasRole('super_admin')) {
            $superAdminCount = User::whereHas('roles', function ($query) {
                $query->where('name', 'super_admin');
            })->count();

            if ($superAdminCount <= 1) {
                return [
                    'can_delete' => false,
                    'reason' => 'last_super_admin',
                    'message' => 'Cannot delete the last super admin account. At least one super admin must remain in the system.'
                ];
            }
        }

        return [
            'can_delete' => true,
            'reason' => null,
            'message' => null
        ];
    }

    /**
     * Get admin statistics
     */
    public static function getAdminStats(): array
    {
        $totalUsers = User::count();
        $superAdminCount = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->count();

        $otherAdminCount = User::whereHas('roles', function ($query) {
            $query->whereIn('name', [
                'mezmur_office_admin',
                'tmhrt_office_admin',
                'distance_admin',
                'gngnunet_office_admin',
                'young_tmhrt_admin'
            ]);
        })->count();

        return [
            'total_users' => $totalUsers,
            'super_admin_count' => $superAdminCount,
            'other_admin_count' => $otherAdminCount,
            'total_admin_count' => $superAdminCount + $otherAdminCount,
            'regular_user_count' => $totalUsers - $superAdminCount - $otherAdminCount
        ];
    }

    /**
     * Check if current user can manage another user
     */
    public static function canManageUser(User $currentUser, User $targetUser): bool
    {
        // Super admins can manage everyone
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }

        // Users cannot manage themselves (except for self-update)
        if ($currentUser->id === $targetUser->id) {
            return false;
        }

        // Check role hierarchy
        $currentUserRole = $currentUser->getRoleNames()->first();
        $targetUserRole = $targetUser->getRoleNames()->first();

        // Define role hierarchy (higher number = higher privilege)
        $roleHierarchy = [
            'super_admin' => 100,
            'mezmur_office_admin' => 80,
            'tmhrt_office_admin' => 80,
            'distance_admin' => 80,
            'gngnunet_office_admin' => 80,
            'young_tmhrt_admin' => 80,
            'mezmur_office_coordinator' => 60,
            'tmhrt_office_coordinator' => 60,
            'distance_coordinator' => 60,
            'gngnunet_office_coordinator' => 60,
            'teacher' => 40,
            'student' => 20,
        ];

        $currentUserLevel = $roleHierarchy[$currentUserRole] ?? 0;
        $targetUserLevel = $roleHierarchy[$targetUserRole] ?? 0;

        return $currentUserLevel > $targetUserLevel;
    }

    /**
     * Get users that current user can manage
     */
    public static function getManageableUsers(User $currentUser): array
    {
        $query = User::query();

        // If not super admin, filter by role hierarchy
        if (!$currentUser->hasRole('super_admin')) {
            $currentUserRole = $currentUser->getRoleNames()->first();
            
            // Define which roles each admin can manage
            $manageableRoles = match ($currentUserRole) {
                'mezmur_office_admin' => ['mezmur_office_coordinator', 'teacher', 'student'],
                'tmhrt_office_admin' => ['tmhrt_office_coordinator', 'teacher', 'student'],
                'distance_admin' => ['distance_coordinator', 'teacher', 'student'],
                'gngnunet_office_admin' => ['gngnunet_office_coordinator', 'student'],
                'young_tmhrt_admin' => ['teacher', 'student'],
                default => []
            };

            if (!empty($manageableRoles)) {
                $query->whereHas('roles', function ($q) use ($manageableRoles) {
                    $q->whereIn('name', $manageableRoles);
                });
            } else {
                // If no manageable roles, return empty result
                return [];
            }
        }

        return $query->with('roles')->get()->toArray();
    }
}
