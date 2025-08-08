<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trainer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'gender'];

    // Trainer.php
    public function specialties()
    {
        return $this->belongsToMany(MezmurCategoryType::class, 'trainer_specialties', 'trainer_id', 'category_type_id')->withTimestamps();
    }

}

