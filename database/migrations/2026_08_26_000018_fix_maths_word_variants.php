<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('words')
            ->where('id', 132)
            ->where('en', 'maths')
            ->update([
                'en_variants' => json_encode(
                    ['mathematics', 'math'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
            ]);
    }

    public function down(): void
    {
        DB::table('words')
            ->where('id', 132)
            ->where('en', 'maths')
            ->update([
                'en_variants' => json_encode(
                    ['Mathematics (Math)'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
            ]);
    }
};
