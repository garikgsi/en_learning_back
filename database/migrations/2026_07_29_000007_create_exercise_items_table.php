<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')
                ->constrained('exercise');
            $table->foreignId('word_id')
                ->constrained('words');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_items');
    }
};
