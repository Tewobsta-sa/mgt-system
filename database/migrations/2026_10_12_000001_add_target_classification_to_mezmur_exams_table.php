<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mezmur_exams', function (Blueprint $table) {
            if (!Schema::hasColumn('mezmur_exams', 'target_classification')) {
                $table->string('target_classification')->nullable()->after('ministry_id')
                    ->comment('e.g. htsanat, maekelawyan, wetatoch — null means all students');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mezmur_exams', function (Blueprint $table) {
            if (Schema::hasColumn('mezmur_exams', 'target_classification')) {
                $table->dropColumn('target_classification');
            }
        });
    }
};
