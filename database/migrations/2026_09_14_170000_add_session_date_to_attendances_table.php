<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('attendances', 'session_date')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->date('session_date')->nullable()->after('student_id');
            });

            // Backfill existing records with marked_at date
            DB::statement("UPDATE `attendances` SET `session_date` = DATE(`marked_at`) WHERE `session_date` IS NULL");
        }

        // Update unique index to include session_date
        Schema::table('attendances', function (Blueprint $table) {
            // Add dedicated index on assignment_id so MySQL FK requirement remains satisfied
            try {
                $table->index('assignment_id', 'attendances_assignment_id_idx');
            } catch (\Throwable $e) {}

            // Drop old unique constraint if present
            try {
                $table->dropUnique('attendances_assignment_id_student_id_unique');
            } catch (\Throwable $e) {
                try {
                    $table->dropUnique(['assignment_id', 'student_id']);
                } catch (\Throwable $e2) {}
            }

            // Create new compound unique constraint
            try {
                $table->unique(['assignment_id', 'student_id', 'session_date'], 'attendances_session_student_unique');
            } catch (\Throwable $e) {
                // Ignore if already exists
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            try {
                $table->dropUnique('attendances_session_student_unique');
                $table->unique(['assignment_id', 'student_id']);
            } catch (\Throwable $e) {}

            if (Schema::hasColumn('attendances', 'session_date')) {
                $table->dropColumn('session_date');
            }
        });
    }
};
