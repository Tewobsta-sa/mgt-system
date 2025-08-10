<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentCourse extends Model
{
    protected $fillable = [
        'assignment_id', 'course_id', 'teacher_id', 'default_period_order'
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
