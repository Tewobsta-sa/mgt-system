<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = ['course_id', 'title', 'max_score', 'weight', 'type'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }
}
