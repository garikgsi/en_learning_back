<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('word_repeats', function (Blueprint $table) {
            $table->foreignId('exercise_id')
                ->constrained('exercise');
        });
    }

    public function down(): void
    {
        Schema::table('word_repeats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exercise_id');
        });
    }
};
