<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'promotion_status')) {
                $table->string('promotion_status')->default('eligible')->after('status');
            }
            if (!Schema::hasColumn('students', 'nominated_by')) {
                $table->foreignId('nominated_by')->nullable()->constrained('users')->nullOnDelete()->after('promotion_status');
            }
            if (!Schema::hasColumn('students', 'nominated_at')) {
                $table->timestamp('nominated_at')->nullable()->after('nominated_by');
            }
            if (!Schema::hasColumn('students', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('nominated_at');
            }
            if (!Schema::hasColumn('students', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('students', 'target_section_id')) {
                $table->foreignId('target_section_id')->nullable()->constrained('sections')->nullOnDelete()->after('approved_at');
            }
            if (!Schema::hasColumn('students', 'promotion_notes')) {
                $table->text('promotion_notes')->nullable()->after('target_section_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $columns = ['promotion_status', 'nominated_by', 'nominated_at', 'approved_by', 'approved_at', 'target_section_id', 'promotion_notes'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
