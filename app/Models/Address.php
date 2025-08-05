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
        'subcity', 'district', 'special_place', 'house_number'
    ];
}
