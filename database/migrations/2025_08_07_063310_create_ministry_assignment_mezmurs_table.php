<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ministry_assignment_mezmurs', function (Blueprint $t) {
            $t->foreignId('ministry_assignment_id')->constrained('ministry_assignments')->cascadeOnDelete();
            $t->foreignId('mezmur_id')->constrained('mezmurs')->cascadeOnDelete();
            $t->primary(['ministry_assignment_id','mezmur_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministry_assignment_mezmurs');
    }
};
