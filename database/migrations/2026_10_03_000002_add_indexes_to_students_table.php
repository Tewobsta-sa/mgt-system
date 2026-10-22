<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index('status', 'students_status_idx');
            $table->index('is_flagged', 'students_is_flagged_idx');
            $table->index('is_mezmur', 'students_is_mezmur_idx');
            $table->index('is_night', 'students_is_night_idx');
            $table->index('classification', 'students_classification_idx');
            $table->index('created_at', 'students_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_status_idx');
            $table->dropIndex('students_is_flagged_idx');
            $table->dropIndex('students_is_mezmur_idx');
            $table->dropIndex('students_is_night_idx');
            $table->dropIndex('students_classification_idx');
            $table->dropIndex('students_created_at_idx');
        });
    }
};
