<?php

use App\Services\Dictionary\DictionaryWordSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sanitizer = app(DictionaryWordSanitizer::class);

        DB::table('words')
            ->select(['id', 'ru', 'en', 'ru_variants', 'en_variants'])
            ->orderBy('id')
            ->chunkById(200, function ($words) use ($sanitizer): void {
                foreach ($words as $word) {
                    $clean = $sanitizer->sanitize([
                        'ru' => $word->ru,
                        'en' => $word->en,
                        'ru_variants' => $this->decodeVariants($word->ru_variants),
                        'en_variants' => $this->decodeVariants($word->en_variants),
                    ]);

                    DB::table('words')->where('id', $word->id)->update([
                        'ru' => $clean['ru'],
                        'en' => $clean['en'],
                        'ru_variants' => json_encode(
                            $clean['ru_variants'],
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                        ),
                        'en_variants' => json_encode(
                            $clean['en_variants'],
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                        ),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The discarded punctuation cannot be reconstructed reliably.
    }

    /** @return list<string> */
    private function decodeVariants(?string $variants): array
    {
        if ($variants === null || $variants === '') {
            return [];
        }

        return json_decode($variants, true, 512, JSON_THROW_ON_ERROR);
    }
};
