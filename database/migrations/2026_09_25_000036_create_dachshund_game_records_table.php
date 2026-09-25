<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dachshund_game_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained('users');
            $table->unsignedBigInteger('best_score')->default(0);
            $table->unsignedInteger('games_played')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dachshund_game_records');
    }
};
