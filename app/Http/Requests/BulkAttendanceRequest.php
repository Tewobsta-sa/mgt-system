<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAttendanceRequest extends FormRequest {
    public function authorize(){ return true; }
    public function rules(){
        return [
            'schedule_block_id' => 'required|exists:schedule_blocks,id',
            'rows' => 'required|array|min:1',
            'rows.*.student_id' => 'required|exists:students,id',
            'rows.*.status' => 'required|in:Present,Absent,Late',
        ];
    }
}

