<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model {
    protected $fillable = ['schedule_block_id','student_id','status','marked_by_user_id','marked_at'];
    public function block(){ return $this->belongsTo(ScheduleBlock::class,'schedule_block_id'); }
    public function student(){ return $this->belongsTo(Student::class); }
    public function markedBy(){ return $this->belongsTo(User::class,'marked_by_user_id'); }
}

