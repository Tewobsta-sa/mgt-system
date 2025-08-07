<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MezmurCategory extends Model {
    protected $fillable = ['name','description','category_type_id'];
    public function type(){ return $this->belongsTo(MezmurCategoryType::class,'category_type_id'); }
}

