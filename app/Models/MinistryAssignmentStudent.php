<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinistryAssignmentStudent extends Model {
    protected $fillable = ['ministry_assignment_id','student_id','source'];
    public function assignment(){ return $this->belongsTo(MinistryAssignment::class,'ministry_assignment_id'); }
    public function student(){ return $this->belongsTo(Student::class); }
}
