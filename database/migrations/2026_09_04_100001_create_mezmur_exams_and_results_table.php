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
        if (!Schema::hasTable('mezmur_exams')) {
            Schema::create('mezmur_exams', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->date('exam_date');
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mezmur_exam_results')) {
            Schema::create('mezmur_exam_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mezmur_exam_id')->constrained('mezmur_exams')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->decimal('score', 5, 2)->nullable();
                $table->enum('status', ['passed', 'failed', 'pending'])->default('pending');
                $table->text('notes')->nullable();
                $table->boolean('sent_to_yesew_habt')->default(false);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['mezmur_exam_id', 'student_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mezmur_exam_results');
        Schema::dropIfExists('mezmur_exams');
    }
};
