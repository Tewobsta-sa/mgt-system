<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LogCrudOperations
{
    /**
     * Fields that must never be written to activity_logs — anyone who can read
     * the admin log UI must not be able to harvest credentials or secrets.
     */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'new_password', 'new_password_confirmation',
        'security_answer', 'refresh_token', 'token', 'access_token',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log POST, PUT, PATCH, DELETE
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Do not log authentication requests or log requests to avoid noise/loops
            if (!$request->is('api/login') && !$request->is('api/refresh') && !$request->is('api/admin/logs')) {
                $user = Auth::guard('sanctum')->user();

                $payload = collect($request->except(self::SENSITIVE_KEYS))
                    ->map(fn ($value) => $value instanceof \Illuminate\Http\UploadedFile
                        ? '[file: ' . $value->getClientOriginalName() . ']'
                        : $value)
                    ->all();

                $details = [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'payload' => $payload,
                    'status_code' => $response->getStatusCode(),
                ];

                ActivityLog::create([
                    'user_id' => $user ? $user->id : null,
                    'action' => $request->method(), 
                    'model_type' => 'HTTP Request', 
                    'model_id' => null,
                    'details' => $details,
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        return $response;
    }
}
