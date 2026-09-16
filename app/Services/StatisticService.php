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

        // 1. Basic Counts across all active sections & students
        $stats['total_students'] = Student::count();
        $stats['verified_students'] = Student::where('is_verified', true)->count();
        $stats['pending_verification'] = max(0, $stats['total_students'] - $stats['verified_students']);
        $stats['mezmur_members'] = Student::where('is_mezmur', true)->count();
        $stats['active_sections'] = Section::count();

        // 2. Attendance Trend (Last 7 days, executed in a single query)
        $startDate = Carbon::today()->subDays(6)->startOfDay();
        $endDate = Carbon::today()->endOfDay();

        $countsByDate = Attendance::select(DB::raw('DATE(marked_at) as date_val'), DB::raw('COUNT(*) as total'))
            ->whereBetween('marked_at', [$startDate, $endDate])
            ->where('status', 'Present')
            ->groupBy(DB::raw('DATE(marked_at)'))
            ->pluck('total', 'date_val');

        $attendanceTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $key = $date->toDateString();
            $attendanceTrend[] = [
                'date' => $date->format('M d'),
                'count' => (int) ($countsByDate[$key] ?? 0)
            ];
        }
        $stats['attendance_trend'] = $attendanceTrend;

        // 3. Section Distribution
        $stats['section_distribution'] = Section::withCount('students')
            ->get()
            ->map(function ($section) {
                return [
                    'name' => $section->name,
                    'count' => $section->students_count
                ];
            });

        // 4. Recent Activities (with null-safe user handling and descriptive domain categories)
        if ($user && $user->hasRole('super_admin')) {
            $stats['recent_logs'] = ActivityLog::with('user')
                ->latest()
                ->take(6)
                ->get()
                ->map(function ($log) {
                    $meta = self::resolveActionMetadata($log);
                    return [
                        'id' => $log->id,
                        'user' => $log->user?->name ?? 'System',
                        'action' => $meta['action'],
                        'category' => $meta['category'],
                        'badge_type' => $meta['badge_type'],
                        'time' => $log->created_at ? $log->created_at->diffForHumans() : ''
                    ];
                });
        }

        return $stats;
    }

    public static function resolveActionMetadata($log): array
    {
        $action = strtoupper($log->action ?? '');
        $url = $log->details['url'] ?? '';
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (str_contains($path, '/attendance/scan') || str_contains($path, '/attendance/mark')) {
            return [
                'action' => 'አቴንዳንስ ተመዝግቧል (Marked Attendance)',
                'category' => 'አቴንዳንስ (Attendance)',
                'badge_type' => 'attendance',
            ];
        }
        if (str_contains($path, '/attendance')) {
            return [
                'action' => 'የአቴንዳንስ መረጃ (Attendance Action)',
                'category' => 'አቴንዳንስ (Attendance)',
                'badge_type' => 'attendance',
            ];
        }
        if (str_contains($path, '/promotions/nominate')) {
            return [
                'action' => 'ለደረጃ እድገት እጩ ቀርቧል (Nominated for Promotion)',
                'category' => 'ደረጃ እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/promotions/endorse')) {
            return [
                'action' => 'የደረጃ እድገት ተረጋግጧል (Endorsed Promotion)',
                'category' => 'ደረጃ እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/promotions/approve')) {
            return [
                'action' => 'የደረጃ እድገት ጸድቋል (Approved Promotion)',
                'category' => 'ደረጃ እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/promotions/reject')) {
            return [
                'action' => 'የደረጃ እድገት ተመላሽ ተደርጓል (Returned Promotion)',
                'category' => 'ደረጃ እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/students')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ ተማሪ ተመዝግቧል (Registered Student)',
                    'category' => 'ምዝገባ (Registration)',
                    'badge_type' => 'create',
                ];
            }
            if ($action === 'PUT' || $action === 'PATCH') {
                return [
                    'action' => 'የተማሪ መረጃ ተሻሽሏል (Updated Student)',
                    'category' => 'ማሻሻያ (Update)',
                    'badge_type' => 'update',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'ተማሪ ተሰርዟል (Deleted Student)',
                    'category' => 'ስረዛ (Deletion)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'የተማሪ አስተዳደር (Student Operation)',
                'category' => 'ተማሪ (Student)',
                'badge_type' => 'update',
            ];
        }
        if (str_contains($path, '/grades')) {
            return [
                'action' => 'የፈተና ውጤት ተመዝግቧል (Recorded Grades)',
                'category' => 'ውጤት (Grading)',
                'badge_type' => 'grade',
            ];
        }
        if (str_contains($path, '/assignments') || str_contains($path, '/schedule')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ መርሐ-ግብር ተዘጋጅቷል (Created Schedule)',
                    'category' => 'መርሐ-ግብር (Schedule)',
                    'badge_type' => 'schedule',
                ];
            }
            return [
                'action' => 'መርሐ-ግብር ተሻሽሏል (Updated Schedule)',
                'category' => 'መርሐ-ግብር (Schedule)',
                'badge_type' => 'update',
            ];
        }
        if (str_contains($path, '/mezmur')) {
            return [
                'action' => 'የመዝሙር አገልግሎት ክንውን (Mezmur Ministry Action)',
                'category' => 'መዝሙር (Mezmur)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/users') || str_contains($path, '/admin/users')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ ተጠቃሚ ተመዝግቧል (Created User)',
                    'category' => 'ተጠቃሚ (New User)',
                    'badge_type' => 'create',
                ];
            }
            if ($action === 'PUT') {
                return [
                    'action' => 'የተጠቃሚ መረጃ ተሻሽሏል (Updated User)',
                    'category' => 'ተጠቃሚ (User Update)',
                    'badge_type' => 'update',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'ተጠቃሚ ተሰርዟል (Deleted User)',
                    'category' => 'ተጠቃሚ (User Deletion)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'የተጠቃሚ አስተዳደር (User Operation)',
                'category' => 'ተጠቃሚ (Account)',
                'badge_type' => 'update',
            ];
        }
        if (str_contains($path, '/courses')) {
            return [
                'action' => 'የትምህርት ኮርስ ክንውን (Course Action)',
                'category' => 'ኮርስ (Course)',
                'badge_type' => 'schedule',
            ];
        }
        if (str_contains($path, '/sections')) {
            return [
                'action' => 'የክፍል አስተዳደር ክንውን (Section Action)',
                'category' => 'ክፍል (Section)',
                'badge_type' => 'schedule',
            ];
        }

        return match ($action) {
            'POST' => [
                'action' => 'አዲስ መረጃ ተመዝግቧል (Created Record)',
                'category' => 'ምዝገባ (Registration)',
                'badge_type' => 'create',
            ],
            'PUT', 'PATCH' => [
                'action' => 'መረጃ ተሻሽሏል (Updated Record)',
                'category' => 'ማሻሻያ (Update)',
                'badge_type' => 'update',
            ],
            'DELETE' => [
                'action' => 'መረጃ ተሰርዟል (Deleted Record)',
                'category' => 'ስረዛ (Deletion)',
                'badge_type' => 'delete',
            ],
            default => [
                'action' => $action ?: 'የስርዓት ክንውን (System Action)',
                'category' => 'ስርዓት (System)',
                'badge_type' => 'info',
            ],
        };
    }

    public static function formatActionDescription($log): string
    {
        return self::resolveActionMetadata($log)['action'];
    }
}
