<?php

namespace App\Http\Controllers;

use App\Services\StatisticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Core stats are identical for all roles — share a short-lived cache
        // (60s) across users. Admin-only recent_logs stay outside the cache.
        $stats = Cache::remember(
            'dashboard_stats_core',
            60,
            fn () => StatisticService::getDashboardStats(null)
        );

        if ($user->hasRole('super_admin')) {
            $stats['recent_logs'] = StatisticService::recentLogs();
        }

        return response()->json($stats);
    }
}
