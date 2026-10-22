<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    protected $fillable = [
        'student_id',
        'city',
        'subcity',
        'district',
        'woreda',
        'kebele',
        'special_place',
        'house_number',
        'house_no',
    ];
}
