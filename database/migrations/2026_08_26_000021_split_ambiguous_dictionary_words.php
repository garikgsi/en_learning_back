<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            if (! DB::table('words')->where('id', 60)->exists()) {
                return;
            }

            $this->mergeWord(539, 60);
            $this->mergeWord(495, 105);
            $this->mergeWord(721, 336);
            $this->mergeWord(540, 255);

            $this->updateWord(60, 'большой', 'big', [], ['large']);
            $this->updateWord(105, 'замечательный', 'great', [], ['brilliant']);
            $this->updateWord(
                109,
                'очаровательный',
                'cute',
                ['милый', 'симпатичный'],
            );
            $this->updateWord(255, 'милый', 'nice');
            $this->updateWord(336, 'умный', 'clever', [], ['smart']);
            $this->updateWord(591, 'великий', 'great');
            $this->updateWord(919, 'наслаждаться', 'enjoy');
            $this->updateWord(
                1276,
                'приятный',
                'pleasant',
                [],
                ['nice', 'enjoyable'],
            );

            DB::table('words')->updateOrInsert(
                ['ru' => 'огромный', 'en' => 'huge'],
                [
                    'ru_variants' => '[]',
                    'en_variants' => json_encode(
                        ['enormous'],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    ),
                    'grade' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }

    public function down(): void
    {
        // Merged exercise history cannot be separated reliably.
    }

    private function mergeWord(int $sourceId, int $targetId): void
    {
        if (! DB::table('words')->where('id', $sourceId)->exists()
            || ! DB::table('words')->where('id', $targetId)->exists()) {
            return;
        }

        $sourceRepetitions = DB::table('user_word_repetition')
            ->where('word_id', $sourceId)
            ->get();

        foreach ($sourceRepetitions as $sourceRepetition) {
            $targetRepetition = DB::table('user_word_repetition')
                ->where('word_id', $targetId)
                ->where('user_id', $sourceRepetition->user_id)
                ->first();

            if ($targetRepetition === null) {
                DB::table('user_word_repetition')
                    ->where('id', $sourceRepetition->id)
                    ->update(['word_id' => $targetId]);

                continue;
            }

            DB::table('user_word_repetition')
                ->where('id', $targetRepetition->id)
                ->update([
                    'is_active' => $targetRepetition->is_active
                        || $sourceRepetition->is_active,
                ]);
            DB::table('user_word_repetition')
                ->where('id', $sourceRepetition->id)
                ->delete();
        }

        DB::table('exercise_items')
            ->where('word_id', $sourceId)
            ->update(['word_id' => $targetId]);
        DB::table('words')->where('id', $sourceId)->delete();
    }

    /**
     * @param  list<string>  $ruVariants
     * @param  list<string>  $enVariants
     */
    private function updateWord(
        int $id,
        string $ru,
        string $en,
        array $ruVariants = [],
        array $enVariants = [],
    ): void {
        DB::table('words')
            ->where('id', $id)
            ->update([
                'ru' => $ru,
                'en' => $en,
                'ru_variants' => json_encode(
                    $ruVariants,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
                'en_variants' => json_encode(
                    $enVariants,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
                'updated_at' => now(),
            ]);
    }
};
