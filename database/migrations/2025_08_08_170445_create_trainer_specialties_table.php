<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trainer_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->foreignId('category_type_id')->constrained('mezmur_category_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trainer_id', 'category_type_id']); // Prevent duplicate specialties
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_specialties');
    }
};
