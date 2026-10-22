<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = ActivityLog::with('user:id,name,username');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('role')) {
            $role = $request->query('role');
            $query->whereHas('user.roles', fn ($q) => $q->where('name', $role));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        // Date range filtering on created_at
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $logs = $query->latest()->paginate($request->query('per_page', 20));

        $logs->getCollection()->transform(function ($log) {
            $meta = \App\Services\StatisticService::resolveActionMetadata($log);
            $log->formatted_action = $meta['action'];
            $log->category = $meta['category'];
            $log->badge_type = $meta['badge_type'];
            return $log;
        });

        return response()->json($logs);
    }
}
