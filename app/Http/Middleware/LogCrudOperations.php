<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LogCrudOperations
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log POST, PUT, PATCH, DELETE
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Do not log authentication requests or log requests to avoid noise/loops
            if (!$request->is('api/login') && !$request->is('api/refresh') && !$request->is('api/admin/logs')) {
                $user = Auth::guard('sanctum')->user();
                
                $details = [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'payload' => $request->except(['password', 'password_confirmation']),
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
