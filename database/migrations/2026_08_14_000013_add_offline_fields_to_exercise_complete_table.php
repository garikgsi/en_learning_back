<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_complete', function (Blueprint $table) {
            $table->uuid('client_attempt_id')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
        });

        DB::table('exercise_complete')
            ->orderBy('id')
            ->chunkById(100, function ($completions): void {
                foreach ($completions as $completion) {
                    DB::table('exercise_complete')
                        ->where('id', $completion->id)
                        ->update([
                            'client_attempt_id' => (string) Str::uuid(),
                            'request_hash' => hash(
                                'sha256',
                                "legacy:{$completion->id}",
                            ),
                            'completed_at' => $completion->created_at,
                        ]);
                }
            });

        Schema::table('exercise_complete', function (Blueprint $table) {
            $table->uuid('client_attempt_id')->nullable(false)->change();
            $table->char('request_hash', 64)->nullable(false)->change();
            $table->timestampTz('completed_at')->nullable(false)->change();
            $table->unique('client_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_complete', function (Blueprint $table) {
            $table->dropUnique(['client_attempt_id']);
            $table->dropColumn([
                'client_attempt_id',
                'request_hash',
                'completed_at',
            ]);
        });
    }
};
