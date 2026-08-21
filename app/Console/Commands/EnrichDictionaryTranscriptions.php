<?php

namespace App\Console\Commands;

use App\Models\Word;
use App\Services\Dictionary\Contracts\PhoneticsDriver;
use Illuminate\Console\Command;
use Throwable;

class EnrichDictionaryTranscriptions extends Command
{
    protected $signature = 'dictionary:enrich-transcriptions {--limit=}';

    protected $description = 'Fill missing English transcriptions from the configured dictionary provider';

    public function handle(
        PhoneticsDriver $phoneticsDriver,
    ): int {
        $query = Word::query()
            ->whereNull('transcription')
            ->where(function ($query): void {
                $query
                    ->whereNull('transcription_checked_at')
                    ->orWhere(
                        'transcription_checked_at',
                        '<=',
                        now()->subDay(),
                    );
            })
            ->orderByRaw(
                'CASE WHEN transcription_checked_at IS NULL THEN 0 ELSE 1 END',
            )
            ->orderBy('id');
        $limit = $this->option('limit');

        if (is_numeric($limit) && (int) $limit > 0) {
            $query->limit((int) $limit);
        }

        $words = $query->get();
        $updated = 0;
        $missing = 0;
        $failed = 0;

        $this->withProgressBar($words, function (Word $word) use (
            $phoneticsDriver,
            &$updated,
            &$missing,
            &$failed,
        ): void {
            try {
                $transcription = $phoneticsDriver
                    ->find($word->en)?->transcription;

                if ($transcription === null) {
                    $word->forceFill([
                        'transcription_checked_at' => now(),
                    ])->save();
                    $missing++;

                    return;
                }

                $word->forceFill([
                    'transcription' => $transcription,
                    'transcription_checked_at' => now(),
                ])->save();
                $updated++;
            } catch (Throwable) {
                $failed++;
            }
        });

        $this->newLine(2);
        $this->info(
            "Updated: {$updated}; unavailable: {$missing}; failed: {$failed}.",
        );

        return self::SUCCESS;
    }
}
