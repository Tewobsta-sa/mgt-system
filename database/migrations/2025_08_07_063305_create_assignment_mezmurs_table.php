<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_mezmurs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $t->foreignId('mezmur_id')->constrained('mezmurs')->cascadeOnDelete();
            $t->timestamps();

            // Keep unique pair so the same mezmur isn't attached twice to same assignment
            $t->unique(['assignment_id', 'mezmur_id'], 'uniq_assignment_mezmur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_mezmurs');
    }
};
