<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleBlockRequest extends FormRequest {
    public function authorize(){ return true; }
    public function rules(){
        return [
            'assignment_id' => 'required|exists:assignments,id',
            'date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:255',

            'items' => 'required|array|min:1',
            'items.*.period_order' => 'required|integer|min:1',
            'items.*.item_type' => 'required|in:Course,Mezmur',
            'items.*.course_id' => 'required_if:items.*.item_type,Course|nullable|exists:courses,id',
            'items.*.mezmur_id' => 'required_if:items.*.item_type,Mezmur|nullable|exists:mezmurs,id',
            'items.*.teacher_id' => 'required|exists:users,id',
            'items.*.start_time' => 'nullable|date_format:H:i',
            'items.*.end_time' => 'nullable|date_format:H:i|after:items.*.start_time',
        ];
    }
}
