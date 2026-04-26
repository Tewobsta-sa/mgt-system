<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProgramType;
use App\Services\SystemInitializationService;
use App\Services\AdminManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            // Check if system needs initialization
            if (SystemInitializationService::needsInitialization()) {
                return response()->json([
                    'error' => 'System needs initialization. Please use /api/system/initialize endpoint to create the first admin user.'
                ], 400);
            }
            
            $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|unique:users',
                'phone_number' => 'nullable|string|max:30',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string',
                'security_question' => 'required|string|max:255',
                'security_answer' => 'required|string|max:255',
                'program_type_ids' => 'array',
                'program_type_ids.*' => 'exists:program_types,id',
            ]);

            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated user'], 401);
            }

            $currentRole = $user->getRoleNames()->first();
            $targetRole = $request->role;

            $allowedRoles = $this->allowedToRegister($currentRole);

            if (!in_array($targetRole, $allowedRoles)) {
                return response()->json(['error' => 'You are not allowed to register this role'], 403);
            }

            // If role is teacher, check program type restrictions
            if ($targetRole === 'teacher' && $request->filled('program_type_ids')) {
                $allowedProgramTypeIds = $this->allowedProgramTypeIdsForAdmin($currentRole);

                foreach ($request->program_type_ids as $ptId) {
                    if (!in_array($ptId, $allowedProgramTypeIds)) {
                        return response()->json(['error' => 'You cannot assign this program type'], 403);
                    }
                }
            }

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'security_question' => $request->security_question,
                'security_answer' => Hash::make($request->security_answer),
            ]);
            $user->assignRole($targetRole);

            // Assign program types if teacher
            if ($targetRole === 'teacher' && $request->filled('program_type_ids')) {
                $user->programTypes()->sync($request->program_type_ids);
            }

            return response()->json(['message' => 'User registered successfully'], 201);
        } catch (\Exception $e) {
            \Log::error('Registration Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function allowedToRegister($role)
    {
        return match ($role) {
            'mezmur_office_admin' => ['mezmur_office_coordinator'],
            'tmhrt_office_admin' => ['teacher', 'tmhrt_office_coordinator'],
            'young_tmhrt_admin' => ['teacher'],  // assuming young admin role added
            'distance_admin' => ['teacher', 'distance_coordinator'],
            'gngnunet_office_admin' => ['gngnunet_office_coordinator'],
            'super_admin' => [
                'mezmur_office_coordinator',
                'teacher', 'tmhrt_office_coordinator',
                'distance_coordinator', 'gngnunet_office_coordinator', 'student',
                'mezmur_office_admin', 'tmhrt_office_admin', 'distance_admin',
                'gngnunet_office_admin', 'super_admin', 'young_tmhrt_admin','young_gngnunet_admin'
            ],
            default => []
        };
    }

    /**
     * Return allowed program_type IDs based on admin role.
     */
    private function allowedProgramTypeIdsForAdmin($adminRole)
        {
            $allProgramTypesRaw = ProgramType::pluck('id', 'name')->toArray();
            $allProgramTypes = [];
            foreach ($allProgramTypesRaw as $name => $id) {
                $allProgramTypes[strtolower($name)] = $id;
            }

            \Log::info('All Program Types:', $allProgramTypes);

            return match ($adminRole) {
                'tmhrt_office_admin' => [
                    $allProgramTypes['regular'] ?? null,
                    $allProgramTypes['young'] ?? null
                ],
                'young_tmhrt_admin' => isset($allProgramTypes['young']) ? [$allProgramTypes['young']] : [],
                'distance_admin' => isset($allProgramTypes['distance']) ? [$allProgramTypes['distance']] : [],
                'super_admin' => array_values($allProgramTypes),
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

    public function profileInfo(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'phone_number' => 'sometimes|nullable|string|max:30',
        ]);

        $user->fill($request->only(['name', 'username', 'phone_number']));
        $user->save();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user], 200);
    }

    public function profilePassword(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password incorrect'], 403);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    public function profileSecurity(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $request->validate([
            'password' => 'required|string',
            'security_question' => 'required|string|max:255',
            'security_answer' => 'required|string|max:255',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Current password incorrect'], 403);
        }

        $user->security_question = $request->security_question;
        $user->security_answer = Hash::make($request->security_answer);
        $user->save();

        return response()->json(['message' => 'Security settings updated successfully'], 200);
    }

    public function update(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'phone_number' => 'sometimes|nullable|string|max:30',
            'password' => 'nullable|string|min:8|confirmed',
            'current_password' => 'required_with:password',
            'security_question' => 'nullable|string|max:255',
            'security_answer' => 'nullable|string|max:255',
            'program_type_ids' => 'array',
            'program_type_ids.*' => 'exists:program_types,id',
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['error' => 'Current password incorrect'], 403);
            }
            $user->password = Hash::make($request->password);
        }

        $user->fill($request->only(['name', 'username', 'phone_number', 'security_question']));

        if ($request->filled('security_answer')) {
            $user->security_answer = Hash::make($request->security_answer);
        }

        $user->save();

        // Validate program types for update as well
        if ($user->hasRole('teacher') && $request->filled('program_type_ids')) {
            $currentRole = $request->user()->getRoleNames()->first();
            $allowedProgramTypeIds = $this->allowedProgramTypeIdsForAdmin($currentRole);

            foreach ($request->program_type_ids as $ptId) {
                if (!in_array($ptId, $allowedProgramTypeIds)) {
                    return response()->json(['error' => 'You cannot assign this program type'], 403);
                }
            }

            $user->programTypes()->sync($request->program_type_ids);
        }

        return response()->json(['message' => 'Profile updated successfully'], 200);
    }

    public function adminUpdate(Request $request, $id)
    {
        $currentUser = Auth::user();
        $user = User::findOrFail($id);
        $targetRole = $user->getRoleNames()->first();

        // Check if management is allowed based on role hierarchy
        $allowedToManage = $this->allowedToRegister($currentUser->getRoleNames()->first());
        
        if (!in_array($targetRole, $allowedToManage) && !$currentUser->hasRole('super_admin')) {
            return response()->json(['error' => 'You are not allowed to update this role'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'phone_number' => 'sometimes|nullable|string|max:30',
            'password' => 'nullable|string|min:8|confirmed',
            'security_question' => 'nullable|string|max:255',
            'security_answer' => 'nullable|string|max:255',
            'program_type_ids' => 'array',
            'program_type_ids.*' => 'exists:program_types,id',
        ]);

        $user->fill($request->only(['name', 'username', 'phone_number', 'security_question']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->filled('security_answer')) {
            $user->security_answer = Hash::make($request->security_answer);
        }

        $user->save();

        if ($user->hasRole('teacher') && $request->filled('program_type_ids')) {
            $user->programTypes()->sync($request->program_type_ids);
        }

        return response()->json(['message' => 'User updated successfully'], 200);
    }

    public function destroy(Request $request, $id)
    {
        $currentUser = Auth::user();
        $userToDelete = User::findOrFail($id);
        $targetRole = $userToDelete->getRoleNames()->first();

        // Check if deletion is allowed based on role hierarchy
        $allowedToManage = $this->allowedToRegister($currentUser->getRoleNames()->first());
        
        if (!in_array($targetRole, $allowedToManage) && !$currentUser->hasRole('super_admin')) {
            return response()->json(['error' => 'You are not allowed to delete this role'], 403);
        }
        
        // Use the service to check if deletion is allowed (extra business logic if any)
        
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function show($id)
    {
        $user = User::with(['roles', 'programTypes'])->findOrFail($id);
        return response()->json($user);
    }

    public function index(Request $request)
    {
        $query = User::with(['roles', 'programTypes']);

        if ($request->filled('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($request->query('per_page', 10));
    }

    /**
     * Get admin statistics
     */
    public function adminStats(Request $request)
    {
        if (!Auth::check() || !Auth::user()->hasRole('super_admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = AdminManagementService::getAdminStats();
        
        return response()->json([
            'stats' => $stats,
            'message' => 'Admin statistics retrieved successfully'
        ]);
    }
}
