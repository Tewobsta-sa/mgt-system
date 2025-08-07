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
        Schema::create('assignment_courses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $t->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('users'); // explicit teacher per course in the template
            $t->unsignedTinyInteger('default_period_order')->nullable(); // preferred order
            $t->timestamps();
            $t->unique(['assignment_id','course_id','teacher_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_courses');
    }
};
