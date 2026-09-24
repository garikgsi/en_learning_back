<?php

namespace App\Services\GrammarRace;

use App\Enums\GrammarRaceGameCode;
use App\Enums\GrammarRacePlayMode;
use App\Enums\GrammarRaceSessionStatus;
use App\Exceptions\GrammarRaceException;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Models\GrammarRaceProfile;
use App\Models\GrammarRaceSession;
use App\Models\User;
use App\Services\EnCoinService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GrammarRaceSessionService
{
    public function __construct(
        private readonly GrammarRaceGameRegistry $games,
        private readonly GrammarRaceDifficultyService $difficultyService,
        private readonly GrammarRaceResultValidator $resultValidator,
        private readonly EnCoinService $enCoinService,
    ) {}

    /** @return array{session: GrammarRaceSession, created: bool} */
    public function start(
        User $user,
        GrammarRaceGameCode $gameCode,
        string $clientRequestId,
        GrammarRacePlayMode $playMode,
    ): array {
        return DB::transaction(function () use ($user, $gameCode, $clientRequestId, $playMode): array {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertGradeIsAvailable($user, $gameCode);
            $existing = GrammarRaceSession::query()
                ->where('user_id', $user->id)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($existing !== null) {
                if ($existing->game_code !== $gameCode || $existing->play_mode !== $playMode) {
                    throw new IdempotencyKeyReusedException;
                }

                return ['session' => $existing->load(['tasks', 'rounds']), 'created' => false];
            }

            GrammarRaceSession::query()
                ->where('user_id', $user->id)
                ->where('game_code', $gameCode->value)
                ->where('play_mode', GrammarRacePlayMode::training->value)
                ->where('status', GrammarRaceSessionStatus::active->value)
                ->update([
                    'status' => GrammarRaceSessionStatus::abandoned,
                    'completed_at' => now(),
                ]);

            $active = GrammarRaceSession::query()
                ->where('user_id', $user->id)
                ->where('game_code', $gameCode->value)
                ->where('play_mode', GrammarRacePlayMode::competitive->value)
                ->where('status', GrammarRaceSessionStatus::active->value)
                ->first();

            if ($active !== null) {
                throw new GrammarRaceException(
                    'ACTIVE_SESSION_EXISTS',
                    409,
                    'Сначала завершите уже начатую грамматическую гонку.',
                );
            }

            $playDate = CarbonImmutable::now(config('grammar_race.timezone'))->toDateString();
            $attemptsUsed = $this->competitiveAttemptsUsed($user, $gameCode, $playDate);
            $dailyAttempts = (int) config('grammar_race.daily_attempts');

            if ($playMode === GrammarRacePlayMode::competitive && $attemptsUsed >= $dailyAttempts) {
                throw new GrammarRaceException(
                    'DAILY_ATTEMPT_LIMIT_REACHED',
                    409,
                    'Все попытки грамматической гонки на сегодня использованы.',
                );
            }

            $attemptNumber = $playMode === GrammarRacePlayMode::competitive ? $attemptsUsed + 1 : null;
            $entryCost = $playMode === GrammarRacePlayMode::training
                ? 0
                : ($attemptNumber <= (int) config('grammar_race.free_attempts')
                ? 0
                : (int) config('grammar_race.paid_attempt_cost'));

            if ($entryCost > 0 && $this->enCoinService->balance($user)['available'] < $entryCost) {
                throw new GrammarRaceException(
                    'INSUFFICIENT_ENCOIN',
                    409,
                    'Недостаточно доступных EnCoin для платной попытки.',
                );
            }

            $game = $this->games->get($gameCode);
            $profile = $this->difficultyService->profile($user, $gameCode);
            $settings = $game->levels()[$profile->current_level] ?? null;
            if (! is_array($settings)) {
                throw new GrammarRaceException(
                    'DIFFICULTY_NOT_AVAILABLE',
                    409,
                    'Уровень сложности игры недоступен.',
                );
            }
            $reactionMultiplier = $this->reactionTimeMultiplier($user);
            $botMinDelayMs = (int) round($settings['bot_min_delay_ms'] * $reactionMultiplier);
            $botMaxDelayMs = (int) round($settings['bot_max_delay_ms'] * $reactionMultiplier);
            $session = GrammarRaceSession::query()->create([
                'user_id' => $user->id,
                'client_request_id' => $clientRequestId,
                'game_code' => $gameCode,
                'play_mode' => $playMode,
                'play_date' => $playDate,
                'attempt_number' => $attemptNumber,
                'entry_cost' => $entryCost,
                'reward' => $playMode === GrammarRacePlayMode::training
                    ? 0
                    : (int) config('grammar_race.win_reward'),
                'difficulty_level' => $profile->current_level,
                'task_mode' => $settings['mode'],
                'bot_error_percent' => $settings['bot_error_percent'],
                'bot_min_delay_ms' => $botMinDelayMs,
                'bot_max_delay_ms' => $botMaxDelayMs,
                'answer_grace_ms' => $settings['answer_grace_ms'],
                'winning_score' => $settings['winning_score'],
                'status' => GrammarRaceSessionStatus::active,
                'rules_version' => config('grammar_race.rules_version'),
                'generator_version' => config('grammar_race.generator_version'),
                'started_at' => now(),
            ]);
            $generatedTasks = $game->generator()->generate(
                $settings,
                (int) config('grammar_race.task_pack_size'),
                $reactionMultiplier,
            );
            $session->tasks()->createMany(array_map(
                fn ($task, int $index): array => $task->toPersistenceArray($index + 1),
                $generatedTasks,
                array_keys($generatedTasks),
            ));

            if ($entryCost > 0) {
                $this->enCoinService->chargeGrammarRace($user, $session);
            }

            return ['session' => $session->load(['tasks', 'rounds']), 'created' => true];
        });
    }

    /**
     * @param  list<array{taskPosition: int, playerAnswer: string|null, playerAnswerMs: int|null, secondPlayerAnswer?: string|null, secondPlayerAnswerMs?: int|null}>  $rounds
     * @return array{session: GrammarRaceSession, created: bool}
     */
    public function complete(
        User $user,
        GrammarRaceSession $session,
        string $clientResultId,
        CarbonImmutable $clientCompletedAt,
        array $rounds,
    ): array {
        $requestHash = $this->resultHash('complete', $session->id, $clientCompletedAt, $rounds);

        return DB::transaction(function () use (
            $user,
            $session,
            $clientResultId,
            $clientCompletedAt,
            $rounds,
            $requestHash,
        ): array {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = GrammarRaceSession::query()
                ->whereKey($session->id)
                ->where('user_id', $user->id)
                ->with('tasks')
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->client_result_id !== null) {
                return $this->existingResult($session, $clientResultId, $requestHash);
            }

            $this->assertResultIdIsUnused($user, $session, $clientResultId);

            $this->assertCanFinish($session, $clientCompletedAt);
            $validated = $this->resultValidator->validate($session, $rounds);
            $studentWon = $validated['studentScore'] === $session->winning_score;

            $session->update([
                'client_result_id' => $clientResultId,
                'result_hash' => $requestHash,
                'status' => $studentWon
                    ? GrammarRaceSessionStatus::studentWon
                    : GrammarRaceSessionStatus::computerWon,
                'student_score' => $validated['studentScore'],
                'computer_score' => $validated['computerScore'],
                'client_completed_at' => $clientCompletedAt,
                'completed_at' => now(),
            ]);
            $session->rounds()->createMany($validated['rounds']);
            if ($session->play_mode === GrammarRacePlayMode::competitive) {
                $this->difficultyService->recordResult($user, $session->game_code, $studentWon);
            }

            if ($studentWon && $session->play_mode === GrammarRacePlayMode::competitive) {
                $this->enCoinService->rewardGrammarRace($user, $session);
            }

            return ['session' => $session->load(['tasks', 'rounds']), 'created' => true];
        });
    }

    /** @return array{session: GrammarRaceSession, created: bool} */
    public function abandon(
        User $user,
        GrammarRaceSession $session,
        string $clientResultId,
        CarbonImmutable $abandonedAt,
    ): array {
        $requestHash = $this->resultHash('abandon', $session->id, $abandonedAt, []);

        return DB::transaction(function () use (
            $user,
            $session,
            $clientResultId,
            $abandonedAt,
            $requestHash,
        ): array {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = GrammarRaceSession::query()
                ->whereKey($session->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->client_result_id !== null) {
                return $this->existingResult($session, $clientResultId, $requestHash);
            }

            $this->assertResultIdIsUnused($user, $session, $clientResultId);

            $this->assertCanFinish($session, $abandonedAt);
            $session->update([
                'client_result_id' => $clientResultId,
                'result_hash' => $requestHash,
                'status' => GrammarRaceSessionStatus::abandoned,
                'client_completed_at' => $abandonedAt,
                'completed_at' => now(),
            ]);
            if ($session->play_mode === GrammarRacePlayMode::competitive) {
                $this->difficultyService->recordResult($user, $session->game_code, false);
            }

            return ['session' => $session->load(['tasks', 'rounds']), 'created' => true];
        });
    }

    /** @return array<string, mixed> */
    public function status(User $user, GrammarRaceGameCode $gameCode): array
    {
        $playDate = CarbonImmutable::now(config('grammar_race.timezone'))->toDateString();
        $attemptsUsed = $this->competitiveAttemptsUsed($user, $gameCode, $playDate);
        $dailyAttempts = (int) config('grammar_race.daily_attempts');
        $profile = GrammarRaceProfile::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode->value)
            ->first();
        $active = GrammarRaceSession::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode->value)
            ->where('play_mode', GrammarRacePlayMode::competitive->value)
            ->where('status', GrammarRaceSessionStatus::active->value)
            ->with(['tasks', 'rounds'])
            ->first();
        $balance = $this->enCoinService->balance($user);
        $game = $this->games->get($gameCode);
        $currentLevel = $profile?->current_level ?? 1;
        $levelSettings = $game->levels()[$currentLevel] ?? [];

        return [
            'gameCode' => $gameCode->value,
            'minGrade' => $this->minimumGrade($gameCode),
            'isAvailable' => $this->isGradeAvailable($user, $gameCode),
            'reactionTimeMultiplier' => $this->reactionTimeMultiplier($user),
            'currentLevel' => $currentLevel,
            'maxLevel' => count($game->levels()),
            'winningScore' => (int) ($levelSettings['winning_score'] ?? config('grammar_race.winning_score')),
            'attemptsUsed' => $attemptsUsed,
            'attemptsRemaining' => max(0, $dailyAttempts - $attemptsUsed),
            'nextEntryCost' => $attemptsUsed >= $dailyAttempts
                ? null
                : ($attemptsUsed < (int) config('grammar_race.free_attempts')
                    ? 0
                    : (int) config('grammar_race.paid_attempt_cost')),
            'balance' => $balance['balance'],
            'available' => $balance['available'],
            'activeSession' => $active,
        ];
    }

    private function minimumGrade(GrammarRaceGameCode $gameCode): int
    {
        return $this->games->get($gameCode)->minimumGrade();
    }

    private function isGradeAvailable(User $user, GrammarRaceGameCode $gameCode): bool
    {
        return $user->grade !== null && $user->grade >= $this->minimumGrade($gameCode);
    }

    private function assertGradeIsAvailable(User $user, GrammarRaceGameCode $gameCode): void
    {
        if (! $this->isGradeAvailable($user, $gameCode)) {
            throw new GrammarRaceException(
                'GRADE_NOT_AVAILABLE',
                403,
                'Игра пока недоступна для вашего класса.',
            );
        }
    }

    private function reactionTimeMultiplier(User $user): float
    {
        $grade = $user->grade ?? 1;

        return round(max(1, 1 + ((7 - $grade) * 0.2)), 2);
    }

    private function competitiveAttemptsUsed(
        User $user,
        GrammarRaceGameCode $gameCode,
        string $playDate,
    ): int {
        return GrammarRaceSession::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode->value)
            ->where('play_mode', GrammarRacePlayMode::competitive->value)
            ->whereDate('play_date', $playDate)
            ->count();
    }

    private function assertCanFinish(GrammarRaceSession $session, CarbonImmutable $clientCompletedAt): void
    {
        if ($session->status !== GrammarRaceSessionStatus::active) {
            throw new GrammarRaceException('SESSION_ALREADY_FINISHED', 409, 'Игровая сессия уже завершена.');
        }

        if ($clientCompletedAt->lessThan($session->started_at)) {
            throw new GrammarRaceException('INVALID_COMPLETION_TIME', 422, 'Время завершения раньше времени начала игры.');
        }

        if ($clientCompletedAt->greaterThan(now()->addMinutes(5))) {
            throw new GrammarRaceException('INVALID_COMPLETION_TIME', 422, 'Время завершения находится в будущем.');
        }

    }

    private function assertResultIdIsUnused(
        User $user,
        GrammarRaceSession $session,
        string $clientResultId,
    ): void {
        if (GrammarRaceSession::query()
            ->where('user_id', $user->id)
            ->where('client_result_id', $clientResultId)
            ->where('id', '!=', $session->id)
            ->exists()) {
            throw new IdempotencyKeyReusedException;
        }
    }

    /** @return array{session: GrammarRaceSession, created: false} */
    private function existingResult(
        GrammarRaceSession $session,
        string $clientResultId,
        string $requestHash,
    ): array {
        if ($session->client_result_id !== $clientResultId || ! hash_equals($session->result_hash ?? '', $requestHash)) {
            throw new IdempotencyKeyReusedException;
        }

        return ['session' => $session->load(['tasks', 'rounds']), 'created' => false];
    }

    /** @param list<array<string, mixed>> $rounds */
    private function resultHash(
        string $operation,
        string $sessionId,
        CarbonImmutable $completedAt,
        array $rounds,
    ): string {
        return hash('sha256', json_encode([
            'operation' => $operation,
            'session_id' => $sessionId,
            'completed_at' => $completedAt->utc()->toISOString(),
            'rounds' => $rounds,
        ], JSON_THROW_ON_ERROR));
    }
}
