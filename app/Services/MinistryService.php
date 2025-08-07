<?php 
namespace App\Services;

use App\Models\{MinistryAssignment, ScheduleBlock, Assignment, Attendance, MezmurStudent};
use Illuminate\Support\Facades\DB;

class MinistryService
{
    /**
     * Auto-pick students with >=60% attendance in mezmur training blocks in a duration.
     */
    public function autoSelectStudents(MinistryAssignment $ma): array
    {
        [$from, $to] = [$ma->duration_start_date, $ma->duration_end_date];

        // All blocks inside duration belonging to MezmurTraining assignments
        $blockIds = ScheduleBlock::query()
            ->whereBetween('date', [$from, $to])
            ->whereHas('assignment', fn($q) => $q->where('type','MezmurTraining'))
            ->pluck('id');

        if ($blockIds->isEmpty()) return [];

        // Eligible population = active mezmur students
        $eligibleIds = MezmurStudent::query()->where('active', true)->pluck('student_id');

        if ($eligibleIds->isEmpty()) return [];

        // Attendance counts
        $rows = Attendance::select('student_id',
                DB::raw("SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) as attended"),
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('schedule_block_id', $blockIds)
            ->whereIn('student_id', $eligibleIds)
            ->groupBy('student_id')
            ->get();

        $selected = [];
        foreach ($rows as $r) {
            if ($r->total > 0 && ($r->attended / $r->total) >= 0.6) {
                $selected[] = (int)$r->student_id;
            }
        }
        return $selected;
    }
}
