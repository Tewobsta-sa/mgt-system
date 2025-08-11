<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProgramType extends Model
{
    use HasFactory;
    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'program_type_user');
    }


    protected $fillable = [
        'name',
        'description' // optional if you have a description
    ];
}
