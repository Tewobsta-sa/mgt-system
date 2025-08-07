<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleItem extends Model {
    protected $fillable = [
        'schedule_block_id','period_order','item_type','course_id','mezmur_id','teacher_id','start_time','end_time'
    ];
    public function block(){ return $this->belongsTo(ScheduleBlock::class,'schedule_block_id'); }
    public function course(){ return $this->belongsTo(Course::class); }
    public function mezmur(){ return $this->belongsTo(Mezmur::class); }
    public function teacher(){ return $this->belongsTo(User::class,'teacher_id'); }
}

