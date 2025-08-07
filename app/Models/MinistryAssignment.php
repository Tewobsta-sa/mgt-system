<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinistryAssignment extends Model {
    protected $fillable = ['ministry_id','duration_start_date','duration_end_date','created_by_user_id'];
    public function ministry(){ return $this->belongsTo(Ministry::class); }
    public function mezmurs(){ return $this->belongsToMany(Mezmur::class,'ministry_assignment_mezmurs'); }
    public function students(){ return $this->hasMany(MinistryAssignmentStudent::class); }
}
