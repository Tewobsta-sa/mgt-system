<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model {
    protected $fillable = [
        'type','section_id','user_id','location','day_of_week','start_time','end_time','active'
    ];
    public function section(){ return $this->belongsTo(Section::class); }
    public function user(){ return $this->belongsTo(User::class); }
    public function mezmurs(){ return $this->belongsToMany(Mezmur::class,'assignment_mezmurs'); }
    public function courses(){ return $this->hasMany(AssignmentCourse::class); }
    public function scheduleBlocks(){ return $this->hasMany(ScheduleBlock::class); }
}

