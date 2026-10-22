<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'semester')) {
                $table->string('semester')->nullable()->after('duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'semester')) {
                $table->dropColumn('semester');
            }
        });
    }
};
