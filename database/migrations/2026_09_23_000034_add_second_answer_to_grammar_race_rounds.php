<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_race_rounds', function (Blueprint $table): void {
            $table->string('second_player_answer', 64)->nullable()->after('player_answer_ms');
            $table->unsignedInteger('second_player_answer_ms')->nullable()->after('second_player_answer');
        });
    }

    public function down(): void
    {
        Schema::table('grammar_race_rounds', function (Blueprint $table): void {
            $table->dropColumn(['second_player_answer', 'second_player_answer_ms']);
        });
    }
};
