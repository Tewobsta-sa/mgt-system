<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:8',
        'role' => 'required|string',
    ]);

    $currentRole = $request->user()->getRoleNames()->first();
    $targetRole = $request->role;

    $allowedRoles = $this->allowedToRegister($currentRole);

    if (!in_array($targetRole, $allowedRoles)) {
        return response()->json(['error' => 'You are not allowed to register this role'], 403);
    }

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'role' => $targetRole,
        'password' => Hash::make($request->password),
    ]);
    $user->assignRole($request->role);

    return response()->json(['message' => 'User registered successfully'], 201);
}
    private function allowedToRegister($role)
{
    return match ($role) {
        'mezmur_office_admin' => ['mezmur_trainer', 'wereb_trainer', 'mezmur_office_coordinator'],
        'tmhrt_office_admin' => ['regular_teacher', 'tmhrt_office_coordinator'],
        'distance_admin' => ['distance_teacher', 'distance_coordinator'],
        'gngnunet_office_admin' => ['gngnunet_office_coordinator'],
        'super_admin' => ['mezmur_trainer', 'wereb_trainer', 'mezmur_office_coordinator', 'regular_teacher', 'tmhrt_office_coordinator', 'distance_teacher', 'distance_coordinator', 'gngnunet_office_coordinator', 'student', 'mezmur_office_admin', 'tmhrt_office_admin', 'distance_admin', 'gngnunet_office_admin', 'super_admin'],
        default => []
    };
}
}
