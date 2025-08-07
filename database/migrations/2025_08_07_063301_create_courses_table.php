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
        Schema::create('courses', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->unsignedInteger('credit_hour')->default(1);
        $t->unsignedInteger('duration')->comment('minutes or hours per period');
        $t->foreignId('program_type_id')->constrained('program_types');
        $t->timestamps();
        $t->unique(['name','program_type_id']);
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
