<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest {
    public function authorize(){ return true; }
    public function rules(){
        return [
            'type' => 'required|in:MezmurTraining,Course',
            'section_id' => 'nullable|exists:sections,id',
            'user_id' => 'required|exists:users,id',
            'location' => 'nullable|string|max:255',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',

            'mezmur_ids' => 'required_if:type,MezmurTraining|array',
            'mezmur_ids.*' => 'integer|exists:mezmurs,id',

            'courses' => 'required_if:type,Course|array',
            'courses.*.course_id' => 'required_with:courses|exists:courses,id',
            'courses.*.teacher_id' => 'required_with:courses|exists:users,id',
            'courses.*.default_period_order' => 'nullable|integer|min:1',
        ];
    }
}

