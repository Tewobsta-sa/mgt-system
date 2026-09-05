<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MezmurExamResult extends Model
{
    protected $fillable = [
        'mezmur_exam_id',
        'student_id',
        'score',
        'status',
        'notes',
        'sent_to_yesew_habt',
        'sent_at',
    ];

    protected $casts = [
        'score' => 'float',
        'sent_to_yesew_habt' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(MezmurExam::class, 'mezmur_exam_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
