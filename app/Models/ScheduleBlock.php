<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleBlock extends Model {
    protected $fillable = ['assignment_id','date','start_time','end_time','location'];
    public function assignment(){ return $this->belongsTo(Assignment::class); }
    public function items(){ return $this->hasMany(ScheduleItem::class); }
    public function attendance(){ return $this->hasMany(Attendance::class); }
}

