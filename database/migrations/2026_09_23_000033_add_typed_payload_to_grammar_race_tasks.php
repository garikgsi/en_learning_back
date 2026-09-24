<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_race_tasks', function (Blueprint $table): void {
            $table->string('task_type')->default('single_choice')->after('position');
            $table->json('payload')->nullable()->after('task_type');
            $table->json('options')->nullable()->after('payload');
            $table->string('correct_answer', 64)->change();
            $table->string('bot_answer', 64)->change();
        });

        Schema::table('grammar_race_rounds', function (Blueprint $table): void {
            $table->string('player_answer', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grammar_race_rounds', function (Blueprint $table): void {
            $table->string('player_answer', 8)->nullable()->change();
        });

        Schema::table('grammar_race_tasks', function (Blueprint $table): void {
            $table->string('correct_answer', 8)->change();
            $table->string('bot_answer', 8)->change();
            $table->dropColumn(['task_type', 'payload', 'options']);
        });
    }
};
