<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_word_repetition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')
                ->constrained('words');
            $table->foreignUuid('user_id')
                ->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'word_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_word_repetition');
    }
};
