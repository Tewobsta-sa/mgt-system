<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMinistryAssignmentRequest extends FormRequest {
    public function authorize(){ return true; }
    public function rules(){
        return [
            'ministry_id' => 'required|exists:ministries,id',
            'duration_start_date' => 'required|date',
            'duration_end_date'   => 'required|date|after_or_equal:duration_start_date',
            'mezmur_ids' => 'required|array|min:1',
            'mezmur_ids.*' => 'integer|exists:mezmurs,id',
        ];
    }
}
