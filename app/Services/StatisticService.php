<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Attendance;
use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Course;
use App\Models\MezmurStudent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticService
{
    public static function getDashboardStats($user)
    {
        $stats = [];

        // 1. Basic Counts
        $stats['total_students'] = Student::whereHas('section', fn($q) => $q->where('program_type_id', 2))->count();
        $stats['verified_students'] = Student::whereHas('section', fn($q) => $q->where('program_type_id', 2))
            ->where('is_verified', true)
            ->count();
        $stats['pending_verification'] = $stats['total_students'] - $stats['verified_students'];
        $stats['mezmur_members'] = Student::whereHas('section', fn($q) => $q->where('program_type_id', 2))
            ->where('is_mezmur', true)
            ->count();
        $stats['active_sections'] = Section::where('program_type_id', 2)->count();

        // 2. Attendance Trend (Last 7 days)
        $attendanceTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = Attendance::whereDate('marked_at', $date)->where('status', 'Present')->count();
            $attendanceTrend[] = [
                'date' => $date->format('M d'),
                'count' => $count
            ];
        }
        $stats['attendance_trend'] = $attendanceTrend;

        // 3. Section Distribution
        $stats['section_distribution'] = Section::where('program_type_id', 2)
            ->withCount('students')
            ->get()
            ->map(function ($section) {
                return [
                    'name' => $section->name,
                    'count' => $section->students_count
                ];
            });

        // 4. Recent Activities
        if ($user->hasRole('super_admin')) {
            $stats['recent_logs'] = ActivityLog::with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'user' => $log->user->name,
                        'action' => $log->action,
                        'time' => $log->created_at->diffForHumans()
                    ];
                });
        }

        return $stats;
    }
}
