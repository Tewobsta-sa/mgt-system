<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MezmurStudent extends Model {
    protected $fillable = ['student_id','active','effective_from','effective_to'];
    public function student(){ return $this->belongsTo(Student::class); }
    protected $casts = ['active'=>'boolean'];
}

