<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'type', 'section_id', 'trainer_id', 'user_id',
        'location', 'day_of_week','scheduled_date', 'start_time', 'end_time', 'active'
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Mezmurs via pivot table assignment_mezmurs
    public function mezmurs(): BelongsToMany
    {
        return $this->belongsToMany(Mezmur::class, 'assignment_mezmurs')
                    ->using(AssignmentMezmur::class)
                    ->withTimestamps();
    }

    // Course assignment records
    public function assignmentCourses(): HasMany
    {
        return $this->hasMany(AssignmentCourse::class);
    }
}
