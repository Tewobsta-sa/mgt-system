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
        Schema::create('ministry_assignment_students', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ministry_assignment_id')->constrained('ministry_assignments')->cascadeOnDelete();
            $t->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $t->enum('source', ['Auto','Manual'])->default('Auto');
            $t->timestamps();
            $t->unique(['ministry_assignment_id', 'student_id'], 'ministry_assignment_student_unique');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministry_assignment_students');
    }
};
