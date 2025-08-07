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
        Schema::create('schedule_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_block_id')->constrained('schedule_blocks')->cascadeOnDelete();
            $t->unsignedTinyInteger('period_order'); // 1,2,3
            $t->enum('item_type', ['Course','Mezmur']);
            // depending on type, fill one of:
            $t->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $t->foreignId('mezmur_id')->nullable()->constrained('mezmurs')->nullOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete(); // teacher/trainer
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->timestamps();
            $t->unique(['schedule_block_id','period_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};
