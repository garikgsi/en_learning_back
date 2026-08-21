<?php

use App\Services\WordVariantSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('words', function (Blueprint $table): void {
            $table->json('ru_variants')->default('[]');
            $table->json('en_variants')->default('[]');
        });

        app(WordVariantSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        Schema::table('words', function (Blueprint $table): void {
            $table->dropColumn(['ru_variants', 'en_variants']);
        });
    }
};
