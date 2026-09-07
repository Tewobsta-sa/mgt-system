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
            if (!Schema::hasColumn('students', 'is_flagged')) {
                $table->boolean('is_flagged')->default(false)->after('status');
                $table->text('flag_reason')->nullable()->after('is_flagged');
                $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete()->after('flag_reason');
                $table->timestamp('flagged_at')->nullable()->after('flagged_by');
            }

            if (!Schema::hasColumn('students', 'is_night')) {
                $table->boolean('is_night')->default(false)->after('classification');
            }

            if (!Schema::hasColumn('students', 'endorsed_by')) {
                $table->foreignId('endorsed_by')->nullable()->constrained('users')->nullOnDelete()->after('nominated_at');
                $table->timestamp('endorsed_at')->nullable()->after('endorsed_by');
                $table->text('endorsement_notes')->nullable()->after('endorsed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $columns = ['is_flagged', 'flag_reason', 'flagged_by', 'flagged_at', 'is_night', 'endorsed_by', 'endorsed_at', 'endorsement_notes'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
