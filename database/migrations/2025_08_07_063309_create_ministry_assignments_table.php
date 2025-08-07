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
        Schema::create('ministry_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ministry_id')->constrained('ministries')->cascadeOnDelete();
            $t->date('duration_start_date');
            $t->date('duration_end_date');
            $t->foreignId('created_by_user_id')->constrained('users');
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministry_assignments');
    }
};
