<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_courses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $t->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedTinyInteger('default_period_order')->nullable();
            $t->timestamps();

            // Unique index — note: because teacher_id can be NULL, multiple NULLs are allowed by MySQL.
            $t->unique(['assignment_id', 'course_id', 'teacher_id'], 'uniq_assignment_course_teacher');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_courses');
    }
};
