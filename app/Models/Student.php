<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function address()
    {
        return $this->hasOne(Address::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    protected $fillable = [
        'student_id',
        'name',
        'christian_name',
        'sex',
        'age',
        'phone_number',
        'email_address',
        'telegram_user_name',
        'section_id',
        'round',
        'educational_level',
        'address_id',
    ];
}
