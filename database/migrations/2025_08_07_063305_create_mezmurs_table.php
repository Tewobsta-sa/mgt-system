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
        Schema::create('mezmurs', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('audio_url')->nullable();
            $t->foreignId('category_id')->constrained('mezmur_categories');
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mezmurs');
    }
};
