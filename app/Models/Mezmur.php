<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mezmur extends Model {
    protected $fillable = ['title','audio_url','category_id'];
    public function category(){ return $this->belongsTo(MezmurCategory::class); }
    public function lyricsParts()
    {
        return $this->hasMany(MezmurLyricsPart::class)->orderBy('order_no');
    }
}

