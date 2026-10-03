<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFlagEvent extends Model
{
    protected $fillable = [
        'student_id',
        'reason',
        'flagged_by',
        'flagged_at',
        'unflagged_by',
        'unflagged_at',
    ];

    protected $casts = [
        'flagged_at' => 'datetime',
        'unflagged_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function flagger()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function unflagger()
    {
        return $this->belongsTo(User::class, 'unflagged_by');
    }
}
