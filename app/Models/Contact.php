<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    public function student()
        {
            return $this->belongsTo(Student::class);
        }
    protected $fillable = [
        'name', 'phone_number', 'type'
    ];
}
