<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('word_repeats');

        Schema::create('lang', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('exercise_complete', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')
                ->constrained('exercise');
            $table->timestamps();
        });

        Schema::create('exercise_items_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_complete_id')
                ->constrained('exercise_complete');
            $table->foreignId('exercise_item_id')
                ->constrained('exercise_items');
            $table->integer('errors_count');
            $table->integer('hints_count');
            $table->foreignId('lang_id')
                ->constrained('lang');
            $table->json('variants');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_items_result');
        Schema::dropIfExists('exercise_complete');
        Schema::dropIfExists('lang');

        Schema::create('word_repeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')
                ->constrained('words');
            $table->foreignUuid('user_id')
                ->constrained('users');
            $table->foreignId('exercise_id')
                ->constrained('exercise');
            $table->integer('errors_count');
            $table->integer('hints_count');
            $table->timestamps();
        });
    }
};
