<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Exceptions\UserExerciseAlreadyCompletedException;
use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\UserWordRepetition;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExerciseCompletionService
{
    /**
     * @param  array<int, array{
     *     exercise_item_id: int,
     *     errors_count: int,
     *     hints_count: int,
     *     lang_id: int,
     *     variants: array<int, string>
     * }>  $itemResults
     * @return array{completion: ExerciseComplete, created: bool}
     */
    public function complete(
        Exercise $exercise,
        array $itemResults,
        string $attemptId,
        CarbonImmutable $completedAt,
    ): array {
        $requestHash = $this->requestHash(
            $exercise->id,
            $itemResults,
            $completedAt,
        );

        try {
            return DB::transaction(function () use (
                $attemptId,
                $completedAt,
                $exercise,
                $itemResults,
                $requestHash,
            ): array {
                $lockedExercise = Exercise::query()
                    ->whereKey($exercise->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = ExerciseComplete::query()
                    ->where('client_attempt_id', $attemptId)
                    ->first();

                if ($existing !== null) {
                    return $this->existingResult($existing, $requestHash);
                }

                if (
                    (int) $lockedExercise->type_id
                        === ExerciseTypeCode::user->value
                    && $lockedExercise->completions()->exists()
                ) {
                    throw new UserExerciseAlreadyCompletedException;
                }

                $this->validateItemResults($lockedExercise, $itemResults);

                $complete = $lockedExercise->completions()->create([
                    'client_attempt_id' => $attemptId,
                    'request_hash' => $requestHash,
                    'completed_at' => $completedAt,
                ]);
                $complete->itemResults()->createMany($itemResults);

                UserWordRepetition::query()
                    ->where('user_id', $lockedExercise->user_id)
                    ->where('is_active', true)
                    ->whereIn(
                        'word_id',
                        $lockedExercise->items()->select('word_id'),
                    )
                    ->update(['is_active' => false]);

                return [
                    'completion' => $complete->load(['itemResults.lang']),
                    'created' => true,
                ];
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            $existing = ExerciseComplete::query()
                ->where('client_attempt_id', $attemptId)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->existingResult($existing, $requestHash);
        }
    }

    /**
     * @param  array<int, array{
     *     exercise_item_id: int,
     *     errors_count: int,
     *     hints_count: int,
     *     lang_id: int,
     *     variants: array<int, string>
     * }>  $itemResults
     */
    private function validateItemResults(
        Exercise $exercise,
        array $itemResults,
    ): void {
        $exerciseItemIds = $exercise->items()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        foreach ($itemResults as $index => $itemResult) {
            if (! $exerciseItemIds->contains($itemResult['exercise_item_id'])) {
                throw ValidationException::withMessages([
                    "exercise_items_result.{$index}.exercise_item_id" => [
                        'The exercise item does not belong to the exercise.',
                    ],
                ]);
            }
        }
    }

    /**
     * @return array{completion: ExerciseComplete, created: false}
     */
    private function existingResult(
        ExerciseComplete $completion,
        string $requestHash,
    ): array {
        if (! hash_equals($completion->request_hash, $requestHash)) {
            throw new IdempotencyKeyReusedException;
        }

        return [
            'completion' => $completion->load(['itemResults.lang']),
            'created' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemResults
     */
    private function requestHash(
        int $exerciseId,
        array $itemResults,
        CarbonImmutable $completedAt,
    ): string {
        return hash('sha256', json_encode([
            'exercise_id' => $exerciseId,
            'completed_at' => $completedAt->utc()->toISOString(),
            'exercise_items_result' => $itemResults,
        ], JSON_THROW_ON_ERROR));
    }
}
