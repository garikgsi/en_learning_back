<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encoin_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('kopecks_per_coin');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        DB::table('encoin_rates')->insert([
            'id' => 1, 'kopecks_per_coin' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('encoin_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users');
            $table->bigInteger('amount');
            $table->unsignedInteger('kopecks_per_coin');
            $table->string('reason');
            $table->string('source_key');
            $table->foreignId('exercise_id')->nullable()->constrained('exercise');
            $table->timestamps();
            $table->unique(['user_id', 'source_key']);
        });
        Schema::create('monetization_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users');
            $table->uuid('client_request_id');
            $table->unsignedInteger('coins');
            $table->unsignedInteger('kopecks_per_coin');
            $table->timestamp('processed_at')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['user_id', 'client_request_id']);
            $table->index(['user_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monetization_requests');
        Schema::dropIfExists('encoin_entries');
        Schema::dropIfExists('encoin_rates');
    }
};
