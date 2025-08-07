<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

namespace App\Http\Controllers;

use App\Http\Requests\BulkAttendanceRequest;
use App\Models\{Attendance, ScheduleBlock, Assignment, Student, MezmurStudent};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function indexByBlock($blockId)
    {
        return Attendance::with('student')->where('schedule_block_id',$blockId)->get();
    }

    public function bulkUpsert(BulkAttendanceRequest $request)
    {
        $data = $request->validated();
        $block = ScheduleBlock::with('assignment.section')->findOrFail($data['schedule_block_id']);

        // Determine eligible roster for this block
        $eligibleQuery = Student::query();

        if ($block->assignment->type === 'Course') {
            // Students of the section
            $eligibleQuery->where('section_id', $block->assignment->section_id);
        } else { // MezmurTraining
            $mezIds = MezmurStudent::where('active', true)->pluck('student_id');
            $eligibleQuery->whereIn('id', $mezIds);
        }
        $eligible = $eligibleQuery->pluck('id')->toArray();

        $userId = Auth::id();

        DB::transaction(function() use ($data,$eligible,$userId) {
            foreach ($data['rows'] as $r) {
                if (!in_array($r['student_id'], $eligible)) {
                    // skip or throw
                    continue;
                }
                Attendance::updateOrCreate(
                    ['schedule_block_id' => $data['schedule_block_id'], 'student_id' => $r['student_id']],
                    ['status' => $r['status'], 'marked_by_user_id' => $userId, 'marked_at' => now()]
                );
            }
        });

        return response()->json(['message'=>'Attendance saved']);
    }
}

