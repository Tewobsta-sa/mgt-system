<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('district')->nullable()->change();
            $table->string('subcity')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('district')->nullable(false)->change();
            $table->string('subcity')->nullable(false)->change();
        });
    }
};
