<?php

namespace App\Http\Middleware;

use App\Services\SystemInitializationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSystemInitialization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (SystemInitializationService::needsInitialization()) {
            return response()->json([
                'error' => 'System needs initialization',
                'message' => 'Please initialize the system by creating the first admin user',
                'initialization_required' => true,
                'endpoints' => [
                    'check_status' => '/api/system/status',
                    'initialize' => '/api/system/initialize',
                    'available_roles' => '/api/system/roles'
                ]
            ], 503);
        }

        return $next($request);
    }
}
