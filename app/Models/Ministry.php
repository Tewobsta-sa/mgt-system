<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ministry extends Model {
    protected $fillable = ['name','ministry_date','location','notes'];
    public function assignments(){ return $this->hasMany(MinistryAssignment::class); }
}
