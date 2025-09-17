<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RefreshToken;
use App\Services\SystemInitializationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SystemInitializationController extends Controller
{
    /**
     * Check if the system needs initialization
     */
    public function checkStatus()
    {
        $status = SystemInitializationService::getInitializationStatus();
        
        if ($status['needs_initialization']) {
            $status['available_roles'] = SystemInitializationService::getInitializationRoles();
        }

        return response()->json($status);
    }

    /**
     * Register the first admin user during system initialization
     */
    public function initialize(Request $request)
    {
        // Only allow if system needs initialization
        if (!SystemInitializationService::needsInitialization()) {
            return response()->json([
                'message' => 'System is already initialized'
            ], 400);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:' . implode(',', array_keys(SystemInitializationService::getInitializationRoles())),
            'security_question' => 'required|string|max:255',
            'security_answer' => 'required|string|max:255',
        ]);

        // Ensure roles exist
        SystemInitializationService::ensureRolesExist();

        // Create the first admin user
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'security_question' => $request->security_question,
            'security_answer' => Hash::make($request->security_answer),
        ]);

        // Assign the selected role
        $user->assignRole($request->role);

        // Create access token (short-lived - 15 minutes)
        $accessToken = $user->createToken('api_token', ['*'], now()->addMinutes(15))->plainTextToken;
        
        // Create refresh token (long-lived - 30 days)
        $refreshToken = RefreshToken::createForUser(
            $user,
            $request->header('User-Agent'),
            $request->ip(),
            $request->header('User-Agent')
        );

        return response()->json([
            'message' => 'System initialized successfully',
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => 900, // 15 minutes in seconds
        ], 201);
    }

    /**
     * Get available roles for initialization
     */
    public function getAvailableRoles()
    {
        if (!SystemInitializationService::needsInitialization()) {
            return response()->json([
                'message' => 'System is already initialized'
            ], 400);
        }

        return response()->json([
            'roles' => SystemInitializationService::getInitializationRoles()
        ]);
    }
}
