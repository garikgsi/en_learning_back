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
                    $missing++;

                    return;
                }

                $word->update(['transcription' => $transcription]);
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
