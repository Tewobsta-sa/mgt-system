<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add is_night to assignments (for separate night schedules)
        Schema::table('assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('assignments', 'is_night')) {
                $table->boolean('is_night')->default(false)->after('type');
            }
        });

        // 2. Expand attendances status enum to include 'Late' and add late_minutes
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'late_minutes')) {
                $table->integer('late_minutes')->nullable()->after('status');
            }
        });

        // Modify enum column on MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `attendances` MODIFY COLUMN `status` ENUM('Present', 'Absent', 'Excused', 'Late') NOT NULL DEFAULT 'Present'");
        }
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'is_night')) {
                $table->dropColumn('is_night');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'late_minutes')) {
                $table->dropColumn('late_minutes');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `attendances` MODIFY COLUMN `status` ENUM('Present', 'Absent', 'Excused') NOT NULL DEFAULT 'Present'");
        }
    }
};
