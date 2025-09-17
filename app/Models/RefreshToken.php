<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'device_name',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new refresh token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Create a new refresh token for a user.
     */
    public static function createForUser(User $user, string $deviceName = null, string $ipAddress = null, string $userAgent = null): self
    {
        // Clean up expired tokens for this user
        self::where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->delete();

        return self::create([
            'user_id' => $user->id,
            'token' => self::generateToken(),
            'expires_at' => now()->addDays(30), // Refresh tokens expire in 30 days
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Find a valid refresh token.
     */
    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Revoke this refresh token.
     */
    public function revoke(): bool
    {
        return $this->delete();
    }

    /**
     * Revoke all refresh tokens for a user.
     */
    public static function revokeAllForUser(User $user): int
    {
        return self::where('user_id', $user->id)->delete();
    }
}
