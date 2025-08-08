<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MezmurLyricsPart extends Model
{
    protected $fillable = ['mezmur_id', 'part_type', 'content', 'repeat_of', 'order_no'];

    // The original lyrics part this one repeats
    public function originalPart()
    {
        return $this->belongsTo(MezmurLyricsPart::class, 'repeat_of');
    }

    // All parts that repeat this one
    public function repeats()
    {
        return $this->hasMany(MezmurLyricsPart::class, 'repeat_of');
    }
}

