<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AssignmentMezmur extends Pivot
{
    protected $table = 'assignment_mezmurs';

    protected $fillable = ['assignment_id', 'mezmur_id'];
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function mezmur()
    {
        return $this->belongsTo(Mezmur::class);
    }
}
