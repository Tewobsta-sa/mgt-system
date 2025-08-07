<?php 
namespace App\Services;

use App\Models\{Assignment, AssignmentCourse, Mezmur, MezmurCategoryType, UserSpecialty};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function create(array $data): Assignment
    {
        return DB::transaction(function() use ($data) {
            // Core assignment
            $assignment = Assignment::create([
                'type' => $data['type'],
                'section_id' => $data['section_id'] ?? null,
                'user_id' => $data['user_id'],
                'location' => $data['location'] ?? null,
                'day_of_week' => $data['day_of_week'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'active' => true,
            ]);

            if ($assignment->type === 'MezmurTraining') {
                // Enforce wereb/mezmur specialty match if any mezmur requires it
                $mezmurs = Mezmur::with('category.type')->whereIn('id', $data['mezmur_ids'])->get();
                $categoryTypeIds = $mezmurs->pluck('category.type.id')->unique()->values();

                // Trainer specialties
                $trainerSpecialties = UserSpecialty::where('user_id', $assignment->user_id)
                    ->pluck('category_type_id')->all();

                foreach ($categoryTypeIds as $ct) {
                    if (!in_array($ct, $trainerSpecialties)) {
                        throw ValidationException::withMessages([
                            'user_id' => ['Trainer lacks required specialty for selected mezmurs.']
                        ]);
                    }
                }
                $assignment->mezmurs()->sync($mezmurs->pluck('id')->all());
            }

            if ($assignment->type === 'Course') {
                if (empty($data['section_id'])) {
                    throw ValidationException::withMessages(['section_id' => ['Section is required for course assignments.']]);
                }
                foreach ($data['courses'] as $row) {
                    AssignmentCourse::create([
                        'assignment_id' => $assignment->id,
                        'course_id' => $row['course_id'],
                        'teacher_id' => $row['teacher_id'],
                        'default_period_order' => $row['default_period_order'] ?? null,
                    ]);
                }
            }

            return $assignment->load(['mezmurs','courses.course','courses.teacher','section']);
        });
    }
}
