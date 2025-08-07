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
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['MezmurTraining', 'Course']); // template type
            $t->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete(); // for Course type
            $t->foreignId('user_id')->constrained('users'); // trainer or teacher (owner/lead)
            $t->string('location')->nullable();
            $t->tinyInteger('day_of_week')->nullable()->comment('0=Sun..6=Sat');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
