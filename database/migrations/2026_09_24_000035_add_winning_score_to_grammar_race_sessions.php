<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_race_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('winning_score')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('grammar_race_sessions', function (Blueprint $table): void {
            $table->dropColumn('winning_score');
        });
    }
};
