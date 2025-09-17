<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request) 
{
    $request->validate([
        'username' => 'required|string',
        'password' => 'required'
    ]);

    $user = User::where('username', $request->username)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    if (!Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Password incorrect'], 401);
    }

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
        'user' => $user,
        'role' => $user->getRoleNames()->first() ?? 'unknown',
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken->token,
        'token_type' => 'Bearer',
        'expires_in' => 900, // 15 minutes in seconds
    ]);
}


    /**
     * Refresh an access token using a refresh token.
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $refreshToken = RefreshToken::findValidToken($request->refresh_token);

        if (!$refreshToken) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $user = $refreshToken->user;

        // Revoke the old refresh token
        $refreshToken->revoke();

        // Create new access token
        $accessToken = $user->createToken('api_token', ['*'], now()->addMinutes(15))->plainTextToken;
        
        // Create new refresh token
        $newRefreshToken = RefreshToken::createForUser(
            $user,
            $request->header('User-Agent'),
            $request->ip(),
            $request->header('User-Agent')
        );

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => 900, // 15 minutes in seconds
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        // Revoke all refresh tokens for this user
        RefreshToken::revokeAllForUser($request->user());

        return response()->json(['message' => 'Logged out successfully']);
    }
}