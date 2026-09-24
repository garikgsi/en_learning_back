<?php

use App\Enums\GrammarRacePlayMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_race_sessions', function (Blueprint $table): void {
            $table->dropUnique('grammar_race_daily_attempt_unique');
            $table->string('play_mode')
                ->default(GrammarRacePlayMode::competitive->value)
                ->after('game_code');
            $table->unsignedTinyInteger('attempt_number')->nullable()->change();
            $table->unique(
                ['user_id', 'game_code', 'play_date', 'play_mode', 'attempt_number'],
                'grammar_race_daily_attempt_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('grammar_race_sessions', function (Blueprint $table): void {
            $table->dropUnique('grammar_race_daily_attempt_unique');
            $table->unsignedTinyInteger('attempt_number')->nullable(false)->change();
            $table->dropColumn('play_mode');
            $table->unique(
                ['user_id', 'game_code', 'play_date', 'attempt_number'],
                'grammar_race_daily_attempt_unique',
            );
        });
    }
};
