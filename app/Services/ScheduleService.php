<?php 
namespace App\Services;

use App\Models\{Assignment, ScheduleBlock, ScheduleItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function createBlockWithItems(array $data): ScheduleBlock
    {
        return DB::transaction(function() use ($data) {
            $assignment = Assignment::findOrFail($data['assignment_id']);

            // Validate item types vs assignment type
            foreach ($data['items'] as $it) {
                if ($assignment->type === 'Course' && $it['item_type'] !== 'Course') {
                    throw ValidationException::withMessages(['items' => ['All items must be Course for Course assignments.']]);
                }
                if ($assignment->type === 'MezmurTraining' && $it['item_type'] !== 'Mezmur') {
                    throw ValidationException::withMessages(['items' => ['All items must be Mezmur for MezmurTraining assignments.']]);
                }
            }

            $block = ScheduleBlock::create([
                'assignment_id' => $assignment->id,
                'date' => $data['date'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'location' => $data['location'] ?? $assignment->location,
            ]);

            foreach ($data['items'] as $it) {
                ScheduleItem::create([
                    'schedule_block_id' => $block->id,
                    'period_order' => $it['period_order'],
                    'item_type' => $it['item_type'],
                    'course_id' => $it['item_type']==='Course' ? $it['course_id'] : null,
                    'mezmur_id' => $it['item_type']==='Mezmur' ? $it['mezmur_id'] : null,
                    'teacher_id' => $it['teacher_id'],
                    'start_time' => $it['start_time'] ?? null,
                    'end_time' => $it['end_time'] ?? null,
                ]);
            }

            return $block->load('items');
        });
    }
}
