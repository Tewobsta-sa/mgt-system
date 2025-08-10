<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['MezmurTraining', 'Course']);
            $t->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();

            // Trainer (used for MezmurTraining) - trainers table should exist
            $t->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();

            // user_id used for Course assignments (teacher)
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('location')->nullable();
            $t->tinyInteger('day_of_week')->nullable()->comment('0=Sun..6=Sat');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
