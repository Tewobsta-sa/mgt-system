<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinistryAssignment extends Model {
    protected $fillable = ['ministry_id','duration_start_date','duration_end_date','created_by_user_id'];
    public function students()
    {
        return $this->belongsToMany(Student::class, 'ministry_assignment_students')
                    ->withPivot('source', 'created_at', 'updated_at');
    }

    public function mezmurs()
    {
        return $this->belongsToMany(Mezmur::class, 'ministry_assignment_mezmurs');
    }

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
