<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')
                ->constrained('users');
            $table->foreignId('type_id')
                ->constrained('exercise_type');
            $table->timestamp('dueDate');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise');
    }
};
