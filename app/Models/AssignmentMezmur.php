<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AssignmentMezmur extends Pivot
{
    protected $table = 'assignment_mezmurs';

    protected $fillable = ['assignment_id', 'mezmur_id'];
}
