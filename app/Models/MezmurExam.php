<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MezmurExam extends Model
{
    protected $fillable = [
        'ministry_id',
        'title',
        'exam_date',
        'description',
        'created_by',
    ];

    public function results()
    {
        return $this->hasMany(MezmurExamResult::class);
    }

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
