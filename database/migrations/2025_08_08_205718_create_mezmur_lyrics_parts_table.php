<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mezmur_lyrics_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mezmur_id')->constrained()->cascadeOnDelete();
            $table->string('part_type'); // chorus, verse, etc.
            $table->text('content')->nullable(); // nullable if this part is a repeat of another
            $table->unsignedBigInteger('repeat_of')->nullable();
            $table->integer('order_no');
            $table->timestamps();

            $table->foreign('repeat_of')->references('id')->on('mezmur_lyrics_parts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mezmur_lyrics_parts');
    }
};
