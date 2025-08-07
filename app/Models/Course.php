<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model {
    protected $fillable = ['name','credit_hour','duration','program_type_id'];
    public function programType(){ return $this->belongsTo(ProgramType::class); }
}

