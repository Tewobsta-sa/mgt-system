<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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
            'username' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string',
            'security_question' => 'required|string|max:255',
            'security_answer' => 'required|string|max:255',
        ]);

        $currentRole = $request->user()->getRoleNames()->first();
        $targetRole = $request->role;

        $allowedRoles = $this->allowedToRegister($currentRole);

        \Log::info('Current Role: ' . $currentRole);
        \Log::info('Allowed Roles: ', $allowedRoles);

        if (!in_array($targetRole, $allowedRoles)) {
            return response()->json(['error' => 'You are not allowed to register this role'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'security_question' => $request->security_question,
            'security_answer' => Hash::make($request->security_answer),
        ]);
        $user->assignRole($targetRole);

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    private function allowedToRegister($role)
    {
        \Log::info('Role passed: ' . $role);
        return match ($role) {
            'mezmur_office_admin' => ['mezmur_office_coordinator'],
            'tmhrt_office_admin' => ['teacher', 'tmhrt_office_coordinator'],
            'distance_admin' => ['teacher', 'distance_coordinator'],
            'gngnunet_office_admin' => ['gngnunet_office_coordinator'],
            'super_admin' => [
                'mezmur_office_coordinator',
                'teacher', 'tmhrt_office_coordinator',
                'distance_coordinator', 'gngnunet_office_coordinator', 'student',
                'mezmur_office_admin', 'tmhrt_office_admin', 'distance_admin',
                'gngnunet_office_admin', 'super_admin'
            ],
            default => []
        };
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'username' => 'required|string|exists:users,username',
            'security_answer' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!Hash::check($request->security_answer, $user->security_answer)) {
            return response()->json(['error' => 'Incorrect security answer'], 403);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password reset successful'], 200);
    }

    public function update(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'current_password' => 'required_with:password',
            'security_question' => 'nullable|string|max:255',
            'security_answer' => 'nullable|string|max:255',
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['error' => 'Current password incorrect'], 403);
            }
            $user->password = Hash::make($request->password);
        }

        $user->fill($request->only(['name', 'username', 'security_question']));

        if ($request->filled('security_answer')) {
            $user->security_answer = Hash::make($request->security_answer);
        }

        $user->save();

        return response()->json(['message' => 'Profile updated successfully'], 200);
    }

    public function adminUpdate(Request $request, $id)
    {
        if (!Auth::check() || !Auth::user()->hasRole('super_admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'security_question' => 'nullable|string|max:255',
            'security_answer' => 'nullable|string|max:255',
        ]);

        $user->fill($request->only(['name', 'username', 'security_question']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->filled('security_answer')) {
            $user->security_answer = Hash::make($request->security_answer);
        }

        $user->save();

        return response()->json(['message' => 'User updated successfully'], 200);
    }

    public function destroy(Request $request, $id)
    {
        if (!Auth::check() || !Auth::user()->hasRole('super_admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%");
        }

        return response()->json($query->paginate(10));
    }
}
