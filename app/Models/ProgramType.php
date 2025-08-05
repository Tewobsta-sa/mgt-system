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

    protected $fillable = [
        'name',
        'description' // optional if you have a description
    ];
}
