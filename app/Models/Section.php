<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory;
    public function programType()
    {
        return $this->belongsTo(ProgramType::class);
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    protected $fillable = [
        'name',
        'program_type_id',
        'order_no'
    ];
}
