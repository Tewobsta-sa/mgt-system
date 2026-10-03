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
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'was_mezmur_before_flag')) {
                $table->boolean('was_mezmur_before_flag')->default(false)->after('is_mezmur');
            }
        });

        if (!Schema::hasTable('student_flag_events')) {
            Schema::create('student_flag_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->text('reason')->nullable();
                $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('flagged_at')->nullable();
                $table->foreignId('unflagged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('unflagged_at')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'flagged_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_flag_events');

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'was_mezmur_before_flag')) {
                $table->dropColumn('was_mezmur_before_flag');
            }
        });
    }
};
