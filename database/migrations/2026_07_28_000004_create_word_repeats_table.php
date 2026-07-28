<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_repeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')
                ->constrained('words');
            $table->foreignUuid('user_id')
                ->constrained('users');
            $table->integer('errors_count');
            $table->integer('hints_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_repeats');
    }
};
