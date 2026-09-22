<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grammar_race_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('game_code');
            $table->unsignedSmallInteger('current_level')->default(1);
            $table->unsignedSmallInteger('max_level')->default(1);
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('games_won')->default(0);
            $table->unsignedInteger('games_at_level')->default(0);
            $table->unsignedInteger('wins_at_level')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'game_code']);
        });

        Schema::create('grammar_race_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->uuid('client_request_id');
            $table->string('game_code');
            $table->date('play_date');
            $table->unsignedTinyInteger('attempt_number');
            $table->unsignedTinyInteger('entry_cost')->default(0);
            $table->unsignedTinyInteger('reward')->default(0);
            $table->unsignedSmallInteger('difficulty_level');
            $table->string('task_mode');
            $table->unsignedTinyInteger('bot_error_percent');
            $table->unsignedInteger('bot_min_delay_ms');
            $table->unsignedInteger('bot_max_delay_ms');
            $table->unsignedInteger('answer_grace_ms');
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('student_score')->default(0);
            $table->unsignedTinyInteger('computer_score')->default(0);
            $table->string('rules_version');
            $table->string('generator_version');
            $table->uuid('client_result_id')->nullable();
            $table->char('result_hash', 64)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('client_completed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_request_id']);
            $table->unique(['user_id', 'client_result_id']);
            $table->unique(['user_id', 'game_code', 'play_date', 'attempt_number'], 'grammar_race_daily_attempt_unique');
            $table->index(['user_id', 'game_code', 'status']);
        });

        Schema::create('grammar_race_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('grammar_race_session_id')->constrained('grammar_race_sessions');
            $table->unsignedSmallInteger('position');
            $table->text('prompt');
            $table->text('translation')->nullable();
            $table->string('correct_answer', 8);
            $table->string('bot_answer', 8);
            $table->unsignedInteger('bot_delay_ms');
            $table->timestamps();
            $table->unique(['grammar_race_session_id', 'position'], 'grammar_race_task_position_unique');
        });

        Schema::create('grammar_race_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('grammar_race_session_id')->constrained('grammar_race_sessions');
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('task_position');
            $table->string('player_answer', 8)->nullable();
            $table->unsignedInteger('player_answer_ms')->nullable();
            $table->string('outcome');
            $table->unsignedTinyInteger('student_score');
            $table->unsignedTinyInteger('computer_score');
            $table->timestamps();
            $table->unique(['grammar_race_session_id', 'sequence'], 'grammar_race_round_sequence_unique');
        });

        Schema::table('encoin_entries', function (Blueprint $table): void {
            $table->foreignUuid('grammar_race_session_id')
                ->nullable()
                ->constrained('grammar_race_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('encoin_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('grammar_race_session_id');
        });
        Schema::dropIfExists('grammar_race_rounds');
        Schema::dropIfExists('grammar_race_tasks');
        Schema::dropIfExists('grammar_race_sessions');
        Schema::dropIfExists('grammar_race_profiles');
    }
};
