<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Attendance;
use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Course;
use App\Models\Ministry;
use App\Models\MezmurExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticService
{
    public static function getDashboardStats($user)
    {
        $stats = [];

        // 1. Student counts (excluding archived graduates from "active" KPIs)
        $stats['total_students'] = Student::count();
        $stats['active_students'] = Student::whereNotIn('status', ['Graduated', 'Inactive'])->count();
        $stats['new_students'] = Student::where('status', 'new')->count();
        $stats['regular_students'] = Student::whereIn('status', ['regular', 'Active'])->count();
        $stats['graduated_students'] = Student::where('status', 'Graduated')->count();
        $stats['inactive_students'] = Student::where('status', 'Inactive')->count();
        $stats['flagged_students'] = Student::where('is_flagged', true)->count();
        $stats['verified_students'] = Student::where('is_verified', true)->count();
        $stats['mezmur_members'] = Student::where('is_mezmur', true)->count();
        $stats['night_shift_students'] = Student::where('is_night', true)->count();
        $stats['active_sections'] = Section::count();
        $stats['total_courses'] = Course::count();
        $stats['total_ministries'] = Ministry::count();
        $stats['total_users'] = User::count();
        $stats['new_registrations_30d'] = Student::where('created_at', '>=', Carbon::now()->subDays(30))->count();

        // 2. Promotion pipeline (Level 1 uses the real eligibility evaluation)
        $stats['promotion'] = [
            'eligible' => PromotionEvaluator::countEligible(),
            'nominated' => Student::whereIn('promotion_status', ['nominated_tmhrt', 'nominated'])->count(),
            'endorsed' => Student::where('promotion_status', 'endorsed_yesew')->count(),
            'promoted' => Student::where('promotion_status', 'promoted')->count(),
        ];

        // 3. Today's attendance snapshot
        $today = Carbon::today();
        $stats['attendance_today'] = [
            'present' => Attendance::whereDate('marked_at', $today)->whereIn('status', ['Present', 'Late'])->count(),
            'absent' => Attendance::whereDate('marked_at', $today)->where('status', 'Absent')->count(),
            'excused' => Attendance::whereDate('marked_at', $today)->where('status', 'Excused')->count(),
        ];

        // 4. Attendance Trend (Last 7 days, executed in a single query)
        $startDate = Carbon::today()->subDays(6)->startOfDay();
        $endDate = Carbon::today()->endOfDay();

        $countsByDate = Attendance::select(DB::raw('DATE(marked_at) as date_val'), DB::raw('COUNT(*) as total'))
            ->whereBetween('marked_at', [$startDate, $endDate])
            ->whereIn('status', ['Present', 'Late'])
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

        // 5. Section Distribution (top sections by enrollment)
        $stats['section_distribution'] = Section::withCount('students')
            ->orderByDesc('students_count')
            ->take(8)
            ->get()
            ->map(function ($section) {
                return [
                    'name' => $section->name,
                    'count' => $section->students_count
                ];
            });

        // 6. Students per program track
        $stats['track_distribution'] = DB::table('students')
            ->leftJoin('sections', 'students.section_id', '=', 'sections.id')
            ->leftJoin('program_types', 'sections.program_type_id', '=', 'program_types.id')
            ->selectRaw("COALESCE(program_types.name, 'Unassigned') as name, COUNT(students.id) as count")
            ->groupBy('program_types.name')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count]);

        // 7. Staff users by role
        $stats['users_by_role'] = DB::table('users')
            ->leftJoin('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                     ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw("COALESCE(roles.name, 'unassigned') as name, COUNT(users.id) as count")
            ->groupBy('roles.name')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count]);

        // 8. Weekly registration trend (last 8 weeks) — one query, bucketed in PHP
        $since = Carbon::today()->subWeeks(7)->startOfWeek();
        $createdDates = Student::where('created_at', '>=', $since)->pluck('created_at');
        $registrations = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekStart = Carbon::today()->subWeeks($i)->startOfWeek();
            $weekEnd = (clone $weekStart)->endOfWeek();
            $registrations[] = [
                'week' => $weekStart->format('M d'),
                'count' => $createdDates->filter(
                    fn ($d) => $d->gte($weekStart) && $d->lte($weekEnd)
                )->count(),
            ];
        }
        $stats['registration_trend'] = $registrations;

        // 9. Recent Activities (with null-safe user handling and descriptive domain categories)
        if ($user && $user->hasRole('super_admin')) {
            $stats['recent_logs'] = self::recentLogs();
        }

        return $stats;
    }

    /**
     * Latest admin-visible activity entries — kept outside the cached stats
     * payload so cached responses can never leak logs to non-admin roles.
     */
    public static function recentLogs(int $limit = 6)
    {
        return ActivityLog::with('user')
            ->latest()
            ->take($limit)
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

    public static function resolveActionMetadata($log): array
    {
        $action = strtoupper($log->action ?? '');
        $url = $log->details['url'] ?? '';
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $statusCode = $log->details['status_code'] ?? null;

        $failed = $statusCode !== null && (int) $statusCode >= 400;
        $suffix = $failed ? ' (ተሳክቶ አልተጠናቀቀም / Failed)' : '';

        $meta = self::resolvePathMetadata($path, $action);
        if ($failed) {
            $meta['action'] .= $suffix;
        }
        return $meta;
    }

    private static function resolvePathMetadata(string $path, string $action): array
    {
        // ── Attendance ────────────────────────────────────────────────
        if (str_contains($path, '/attendance/scan') || str_contains($path, '/attendance/mark')) {
            return [
                'action' => 'አቴንዳንስ ተመዝግቧል (Marked attendance)',
                'category' => 'አቴንዳንስ (Attendance)',
                'badge_type' => 'attendance',
            ];
        }
        if (str_contains($path, '/attendance/bulk')) {
            return [
                'action' => 'የጅምላ አቴንዳንስ ተመዝግቧል (Bulk-marked attendance)',
                'category' => 'አቴንዳንስ (Attendance)',
                'badge_type' => 'attendance',
            ];
        }
        if (str_contains($path, '/attendance')) {
            return [
                'action' => 'የአቴንዳንስ መረጃ ክንውን (Attendance action)',
                'category' => 'አቴንዳንስ (Attendance)',
                'badge_type' => 'attendance',
            ];
        }

        // ── Promotion pipeline ────────────────────────────────────────
        if (str_contains($path, '/promotions/nominate')) {
            return [
                'action' => 'ደረጃ 1፡ ተማሪዎች ለክፍል እድገት እጩ ሆነዋል (Nominated students - Level 1)',
                'category' => 'የክፍል እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/promotions/endorse')) {
            return [
                'action' => 'ደረጃ 2፡ የሰው ሀብት እድገትን አጽድቋል (Endorsed promotion - Level 2)',
                'category' => 'የክፍል እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/promotions/approve')) {
            return [
                'action' => 'ደረጃ 3፡ ተማሪዎች ይፋዊ ተዘዋውረዋል (Approved promotion - Level 3)',
                'category' => 'የክፍል እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }
        if (str_contains($path, '/promotions/reject')) {
            return [
                'action' => 'የክፍል እድገት ተመልሷል (Returned nomination to eligible pool)',
                'category' => 'የክፍል እድገት (Promotion)',
                'badge_type' => 'promotion',
            ];
        }

        // ── Students ──────────────────────────────────────────────────
        if (str_contains($path, '/students')) {
            if (str_contains($path, '/flag') && !str_contains($path, '/unflag')) {
                return [
                    'action' => 'ተማሪ ታግዷል/እገዳ ተጣልበት (Flagged student)',
                    'category' => 'ተማሪ እገዳ (Flagging)',
                    'badge_type' => 'delete',
                ];
            }
            if (str_contains($path, '/unflag')) {
                return [
                    'action' => 'የተማሪ እገዳ ተነስቷል (Unflagged student)',
                    'category' => 'ተማሪ እገዳ (Flagging)',
                    'badge_type' => 'update',
                ];
            }
            if (str_contains($path, '/import')) {
                return [
                    'action' => 'የጅምላ ተማሪ ምዝገባ ተካሂዷል (Bulk student import)',
                    'category' => 'ምዝገባ (Registration)',
                    'badge_type' => 'create',
                ];
            }
            if (str_contains($path, '/bulk-status')) {
                return [
                    'action' => 'የተማሪዎች ሁኔታ በጅምላ ተቀይሯል (Bulk status change)',
                    'category' => 'ማሻሻያ (Update)',
                    'badge_type' => 'update',
                ];
            }
            if (str_contains($path, '/verify') || str_contains($path, '/bulk-verify')) {
                return [
                    'action' => 'ተማሪ ተረጋግጧል (Verified student)',
                    'category' => 'ማረጋገጫ (Verification)',
                    'badge_type' => 'promotion',
                ];
            }
            if (str_contains($path, '/mezmur/assign')) {
                return [
                    'action' => 'ተማሪዎች ወደ መዝሙር ተመድበዋል (Assigned to Mezmur)',
                    'category' => 'መዝሙር (Mezmur)',
                    'badge_type' => 'mezmur',
                ];
            }
            if (str_contains($path, '/mezmur/unassign')) {
                return [
                    'action' => 'ተማሪዎች ከመዝሙር ተወግደዋል (Removed from Mezmur)',
                    'category' => 'መዝሙር (Mezmur)',
                    'badge_type' => 'mezmur',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'ተማሪ ተሰርዟል (Deleted student)',
                    'category' => 'ስረዛ (Deletion)',
                    'badge_type' => 'delete',
                ];
            }
            if ($action === 'PUT' || $action === 'PATCH') {
                return [
                    'action' => 'የተማሪ መረጃ ተሻሽሏል (Updated student)',
                    'category' => 'ማሻሻያ (Update)',
                    'badge_type' => 'update',
                ];
            }
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ ተማሪ ተመዝግቧል (Registered student)',
                    'category' => 'ምዝገባ (Registration)',
                    'badge_type' => 'create',
                ];
            }
            return [
                'action' => 'የተማሪ አስተዳደር ክንውን (Student operation)',
                'category' => 'ተማሪ (Student)',
                'badge_type' => 'update',
            ];
        }

        // ── Grades & assessments ──────────────────────────────────────
        if (str_contains($path, '/grades/import')) {
            return [
                'action' => 'ውጤቶች ከፋይል ገብተዋል (Imported grades)',
                'category' => 'ውጤት (Grading)',
                'badge_type' => 'grade',
            ];
        }
        if (str_contains($path, '/grades')) {
            return [
                'action' => 'የፈተና ውጤት ተመዝግቧል (Recorded grades)',
                'category' => 'ውጤት (Grading)',
                'badge_type' => 'grade',
            ];
        }
        if (str_contains($path, '/assessments')) {
            return [
                'action' => 'የግምገማ ክንውን (Assessment action)',
                'category' => 'ውጤት (Grading)',
                'badge_type' => 'grade',
            ];
        }

        // ── Schedules & assignments ───────────────────────────────────
        if (str_contains($path, '/ministry-assignments') || str_contains($path, '/ministries/bulk-assign')) {
            return [
                'action' => 'ተማሪዎች ወደ አገልግሎት ዘርፍ ተመድበዋል (Ministry assignment)',
                'category' => 'አገልግሎት (Ministry)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/ministries')) {
            if ($action === 'DELETE') {
                return [
                    'action' => 'አገልግሎት ዘርፍ ተሰርዟል (Deleted ministry)',
                    'category' => 'አገልግሎት (Ministry)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'የአገልግሎት ዘርፍ ክንውን (Ministry created/updated)',
                'category' => 'አገልግሎት (Ministry)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/mezmur-exams/bulk-results')) {
            return [
                'action' => 'የመዝሙር ፈተና ውጤቶች ተመዝግበዋል (Saved exam results)',
                'category' => 'መዝሙር ፈተና (Mezmur Exam)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/mezmur-exams/send-passed')) {
            return [
                'action' => 'የፈተና አልፈው የሰው ሀብት ተልከዋል (Forwarded passed students)',
                'category' => 'መዝሙር ፈተና (Mezmur Exam)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/mezmur-exams')) {
            return [
                'action' => 'የመዝሙር ፈተና ክፍለ-ጊዜ ተፈጥሯል (Created exam session)',
                'category' => 'መዝሙር ፈተና (Mezmur Exam)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/assignments') || str_contains($path, '/schedule')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ መርሐ-ግብር ተዘጋጅቷል (Created schedule)',
                    'category' => 'መርሐ-ግብር (Schedule)',
                    'badge_type' => 'schedule',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'መርሐ-ግብር ተሰርዟል (Deleted schedule)',
                    'category' => 'መርሐ-ግብር (Schedule)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'መርሐ-ግብር ተሻሽሏል (Updated schedule)',
                'category' => 'መርሐ-ግብር (Schedule)',
                'badge_type' => 'update',
            ];
        }
        if (str_contains($path, '/mezmur') || str_contains($path, '/mezmurs')) {
            return [
                'action' => 'የመዝሙር አገልግሎት ክንውን (Mezmur ministry action)',
                'category' => 'መዝሙር (Mezmur)',
                'badge_type' => 'mezmur',
            ];
        }
        if (str_contains($path, '/trainers')) {
            return [
                'action' => 'የመዝሙር አሰልጣኝ ክንውን (Trainer action)',
                'category' => 'መዝሙር (Mezmur)',
                'badge_type' => 'mezmur',
            ];
        }

        // ── Reports ───────────────────────────────────────────────────
        if (str_contains($path, '/reports/export')) {
            return [
                'action' => 'ሪፖርት ተወርዷል (Exported report)',
                'category' => 'ሪፖርት (Reports)',
                'badge_type' => 'info',
            ];
        }

        // ── Users & roles ─────────────────────────────────────────────
        if (str_contains($path, '/users') || str_contains($path, '/admin/users') || str_contains($path, '/register')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ ተጠቃሚ ተመዝግቧል (Created user account)',
                    'category' => 'ተጠቃሚ (User)',
                    'badge_type' => 'create',
                ];
            }
            if ($action === 'PUT' || $action === 'PATCH') {
                return [
                    'action' => 'የተጠቃሚ መረጃ ተሻሽሏል (Updated user)',
                    'category' => 'ተጠቃሚ (User)',
                    'badge_type' => 'update',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'ተጠቃሚ ተሰርዟል (Deleted user)',
                    'category' => 'ተጠቃሚ (User)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'የተጠቃሚ አስተዳደር ክንውን (User operation)',
                'category' => 'ተጠቃሚ (Account)',
                'badge_type' => 'update',
            ];
        }
        if (str_contains($path, '/teachers')) {
            return [
                'action' => 'የመምህር አስተዳደር ክንውን (Teacher account action)',
                'category' => 'ተጠቃሚ (User)',
                'badge_type' => 'update',
            ];
        }

        // ── Academic structure ────────────────────────────────────────
        if (str_contains($path, '/courses')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ ኮርስ ተፈጥሯል (Created course)',
                    'category' => 'ኮርስ (Course)',
                    'badge_type' => 'create',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'ኮርስ ተሰርዟል (Deleted course)',
                    'category' => 'ኮርስ (Course)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'ኮርስ ተሻሽሏል (Updated course)',
                'category' => 'ኮርስ (Course)',
                'badge_type' => 'schedule',
            ];
        }
        if (str_contains($path, '/sections')) {
            if ($action === 'POST') {
                return [
                    'action' => 'አዲስ ክፍል ተፈጥሯል (Created section)',
                    'category' => 'ክፍል (Section)',
                    'badge_type' => 'create',
                ];
            }
            if ($action === 'DELETE') {
                return [
                    'action' => 'ክፍል ተሰርዟል (Deleted section)',
                    'category' => 'ክፍል (Section)',
                    'badge_type' => 'delete',
                ];
            }
            return [
                'action' => 'ክፍል ተሻሽሏል (Updated section)',
                'category' => 'ክፍል (Section)',
                'badge_type' => 'schedule',
            ];
        }
        if (str_contains($path, '/program-types')) {
            return [
                'action' => 'የትምህርት ዘርፍ (program) ክንውን (Program action)',
                'category' => 'ክፍል (Section)',
                'badge_type' => 'schedule',
            ];
        }

        return match ($action) {
            'POST' => [
                'action' => 'አዲስ መረጃ ተመዝግቧል (Created record)',
                'category' => 'ምዝገባ (Registration)',
                'badge_type' => 'create',
            ],
            'PUT', 'PATCH' => [
                'action' => 'መረጃ ተሻሽሏል (Updated record)',
                'category' => 'ማሻሻያ (Update)',
                'badge_type' => 'update',
            ],
            'DELETE' => [
                'action' => 'መረጃ ተሰርዟል (Deleted record)',
                'category' => 'ስረዛ (Deletion)',
                'badge_type' => 'delete',
            ],
            default => [
                'action' => $action ?: 'የስርዓት ክንውን (System action)',
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
