<?php

namespace App\Services\GrammarRace;

use App\Enums\GrammarRaceGameCode;
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
        private readonly PersonalPronounTaskGenerator $taskGenerator,
        private readonly GrammarRaceDifficultyService $difficultyService,
        private readonly GrammarRaceResultValidator $resultValidator,
        private readonly EnCoinService $enCoinService,
    ) {}

    /** @return array{session: GrammarRaceSession, created: bool} */
    public function start(
        User $user,
        GrammarRaceGameCode $gameCode,
        string $clientRequestId,
    ): array {
        return DB::transaction(function () use ($user, $gameCode, $clientRequestId): array {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = GrammarRaceSession::query()
                ->where('user_id', $user->id)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($existing !== null) {
                if ($existing->game_code !== $gameCode) {
                    throw new IdempotencyKeyReusedException;
                }

                return ['session' => $existing->load(['tasks', 'rounds']), 'created' => false];
            }

            $active = GrammarRaceSession::query()
                ->where('user_id', $user->id)
                ->where('game_code', $gameCode->value)
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
            $attemptsUsed = GrammarRaceSession::query()
                ->where('user_id', $user->id)
                ->where('game_code', $gameCode->value)
                ->whereDate('play_date', $playDate)
                ->count();
            $dailyAttempts = (int) config('grammar_race.daily_attempts');

            if ($attemptsUsed >= $dailyAttempts) {
                throw new GrammarRaceException(
                    'DAILY_ATTEMPT_LIMIT_REACHED',
                    409,
                    'Все попытки грамматической гонки на сегодня использованы.',
                );
            }

            $attemptNumber = $attemptsUsed + 1;
            $entryCost = $attemptNumber <= (int) config('grammar_race.free_attempts')
                ? 0
                : (int) config('grammar_race.paid_attempt_cost');

            if ($entryCost > 0 && $this->enCoinService->balance($user)['available'] < $entryCost) {
                throw new GrammarRaceException(
                    'INSUFFICIENT_ENCOIN',
                    409,
                    'Недостаточно доступных EnCoin для платной попытки.',
                );
            }

            $profile = $this->difficultyService->profile($user, $gameCode);
            $settings = config("grammar_race.levels.{$profile->current_level}");
            $session = GrammarRaceSession::query()->create([
                'user_id' => $user->id,
                'client_request_id' => $clientRequestId,
                'game_code' => $gameCode,
                'play_date' => $playDate,
                'attempt_number' => $attemptNumber,
                'entry_cost' => $entryCost,
                'reward' => (int) config('grammar_race.win_reward'),
                'difficulty_level' => $profile->current_level,
                'task_mode' => $settings['mode'],
                'bot_error_percent' => $settings['bot_error_percent'],
                'bot_min_delay_ms' => $settings['bot_min_delay_ms'],
                'bot_max_delay_ms' => $settings['bot_max_delay_ms'],
                'answer_grace_ms' => $settings['answer_grace_ms'],
                'status' => GrammarRaceSessionStatus::active,
                'rules_version' => config('grammar_race.rules_version'),
                'generator_version' => config('grammar_race.generator_version'),
                'started_at' => now(),
            ]);
            $session->tasks()->createMany($this->taskGenerator->generate(
                $profile->current_level,
                (int) config('grammar_race.task_pack_size'),
            ));

            if ($entryCost > 0) {
                $this->enCoinService->chargeGrammarRace($user, $session);
            }

            return ['session' => $session->load(['tasks', 'rounds']), 'created' => true];
        });
    }

    /**
     * @param  list<array{taskPosition: int, playerAnswer: string|null, playerAnswerMs: int|null}>  $rounds
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
            $studentWon = $validated['studentScore'] === (int) config('grammar_race.winning_score');

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
            $this->difficultyService->recordResult($user, $session->game_code, $studentWon);

            if ($studentWon) {
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
            $this->difficultyService->recordResult($user, $session->game_code, false);

            return ['session' => $session->load(['tasks', 'rounds']), 'created' => true];
        });
    }

    /** @return array<string, mixed> */
    public function status(User $user, GrammarRaceGameCode $gameCode): array
    {
        $playDate = CarbonImmutable::now(config('grammar_race.timezone'))->toDateString();
        $attemptsUsed = GrammarRaceSession::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode->value)
            ->whereDate('play_date', $playDate)
            ->count();
        $dailyAttempts = (int) config('grammar_race.daily_attempts');
        $profile = GrammarRaceProfile::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode->value)
            ->first();
        $active = GrammarRaceSession::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode->value)
            ->where('status', GrammarRaceSessionStatus::active->value)
            ->with(['tasks', 'rounds'])
            ->first();
        $balance = $this->enCoinService->balance($user);

        return [
            'gameCode' => $gameCode->value,
            'currentLevel' => $profile?->current_level ?? 1,
            'maxLevel' => count(config('grammar_race.levels')),
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
