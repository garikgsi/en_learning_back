<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_info', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained('users');
            $table->integer('first_grade_year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_info');
    }
};
