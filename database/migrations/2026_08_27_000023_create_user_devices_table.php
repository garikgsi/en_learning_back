<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->uuid('installation_id')->unique();
            $table->text('push_token')->unique();
            $table->string('platform', 20);
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index(['user_id', 'notifications_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
