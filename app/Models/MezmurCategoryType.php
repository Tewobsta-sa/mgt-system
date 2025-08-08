<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MezmurCategoryType extends Model {
    protected $fillable = ['name','description'];

    public function trainers()
    {
        return $this->belongsToMany(Trainer::class, 'trainer_specialties', 'category_type_id', 'trainer_id')->withTimestamps();
    }
}

