<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSpecialty extends Model {
    protected $fillable = ['user_id','category_type_id'];
    public function user(){ return $this->belongsTo(User::class); }
    public function categoryType(){ return $this->belongsTo(MezmurCategoryType::class,'category_type_id'); }
}

