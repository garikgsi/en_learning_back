<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Enums\LangCode;
use App\Models\EnCoinEntry;
use App\Models\EnCoinRate;
use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\MonetizationRequest;
use App\Models\User;
use App\Notifications\EnCoinBalanceChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnCoinService
{
    public function rewardCompletion(Exercise $exercise, ExerciseComplete $completion, bool $alreadyCompleted): void
    {
        if ($alreadyCompleted || ! in_array((int) $exercise->type_id, [
            ExerciseTypeCode::daily->value,
            ExerciseTypeCode::weekly->value,
            ExerciseTypeCode::plural->value,
        ], true)
            || ! $this->isFullCompletion($exercise, $completion->itemResults)) {
            return;
        }
        $daily = in_array((int) $exercise->type_id, [
            ExerciseTypeCode::daily->value,
            ExerciseTypeCode::plural->value,
        ], true);
        $deadline = CarbonImmutable::parse($exercise->dueDate->toDateString(), config('encoin.timezone'))->endOfDay();
        $amount = $daily ? 1 + ($completion->completed_at->lessThanOrEqualTo($deadline) ? 1 : 0) : 5;
        $entry = EnCoinEntry::query()->firstOrCreate(
            ['user_id' => $exercise->user_id, 'source_key' => 'exercise:'.$exercise->id],
            ['amount' => $amount, 'kopecks_per_coin' => $this->rate()->kopecks_per_coin, 'reason' => $daily ? 'daily' : 'weekly', 'exercise_id' => $exercise->id],
        );
        if ($entry->wasRecentlyCreated) {
            $this->notifyBalanceChanged($exercise->user, $entry);
        }
        $this->awardCompletedWeeks($exercise->user);
    }

    /** Results must cover every required answer direction. */
    private function isFullCompletion(Exercise $exercise, iterable $results): bool
    {
        $keys = [];
        foreach ($results as $result) {
            $keys[data_get($result, 'exercise_item_id').':'.data_get($result, 'lang_id')] = true;
        }
        $items = $exercise->items;
        if ($items->isEmpty()) {
            return false;
        }
        $languages = (int) $exercise->type_id === ExerciseTypeCode::plural->value
            ? [LangCode::en]
            : [LangCode::en, LangCode::ru];

        foreach ($items as $item) {
            foreach ($languages as $lang) {
                if (! isset($keys[$item->id.':'.$lang->value])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function awardCompletedWeeks(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $weekStarts = Exercise::query()->whereIn('id', EnCoinEntry::query()
                ->where('user_id', $user->id)->whereIn('reason', ['daily', 'weekly'])->select('exercise_id'))
                ->pluck('dueDate')->map(fn ($date) => CarbonImmutable::parse($date)->startOfWeek()->toDateString())->unique();
            foreach ($weekStarts as $start) {
                if (EnCoinEntry::query()->where('user_id', $user->id)->where('source_key', 'week:'.$start)->exists()) {
                    continue;
                }
                $week = CarbonImmutable::parse($start);
                $exercises = $user->exercises()->whereIn('type_id', [
                    ExerciseTypeCode::daily->value,
                    ExerciseTypeCode::weekly->value,
                    ExerciseTypeCode::plural->value,
                ])
                    ->whereBetween('dueDate', [$week, $week->endOfWeek()])
                    ->with(['items', 'completions.itemResults'])->get();
                if ($exercises->isNotEmpty() && $exercises->every(fn (Exercise $exercise): bool => $exercise->completions->contains(fn (ExerciseComplete $complete): bool => $this->isFullCompletion($exercise, $complete->itemResults)))) {
                    $entry = EnCoinEntry::query()->create([
                        'user_id' => $user->id, 'source_key' => 'week:'.$start,
                        'amount' => 5, 'kopecks_per_coin' => $this->rate()->kopecks_per_coin, 'reason' => 'weekly_bonus',
                    ]);
                    $this->notifyBalanceChanged($user, $entry);
                }
            }
        });
    }

    public function rate(): EnCoinRate
    {
        return EnCoinRate::query()->findOrFail(1);
    }

    /** @return array{balance: int, reserved: int, available: int, rublesPerCoin: float|int, withdrawalThreshold: int, totalEarnedCoins: int, totalEarnedRubles: float|int} */
    public function balance(User $user): array
    {
        $balance = (int) EnCoinEntry::query()->where('user_id', $user->id)->sum('amount');
        $reserved = (int) MonetizationRequest::query()->where('user_id', $user->id)->whereNull('processed_at')->sum('coins');
        $rewards = EnCoinEntry::query()->where('user_id', $user->id)->where('amount', '>', 0);
        $earnedCoins = (int) (clone $rewards)->sum('amount');
        $earned = $rewards->selectRaw('COALESCE(SUM(amount * kopecks_per_coin), 0) as earned')->value('earned');

        return [
            'balance' => $balance, 'reserved' => $reserved, 'available' => $balance - $reserved,
            'rublesPerCoin' => $this->rate()->kopecks_per_coin / 100,
            'totalEarnedCoins' => $earnedCoins,
            'totalEarnedRubles' => (int) $earned / 100,
            'withdrawalThreshold' => (int) config('encoin.withdrawal_threshold'),
        ];
    }

    /** @return array{request: MonetizationRequest, created: bool} */
    public function requestWithdrawal(User $user, int $coins, string $clientId): array
    {
        return DB::transaction(function () use ($user, $coins, $clientId): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = MonetizationRequest::query()->where('user_id', $user->id)->where('client_request_id', $clientId)->first();
            if ($existing !== null) {
                if ($existing->coins !== $coins) {
                    throw ValidationException::withMessages(['clientRequestId' => 'Этот запрос уже использован для другой суммы.']);
                }

                return ['request' => $existing, 'created' => false];
            }
            $this->awardCompletedWeeks($user);
            $balance = $this->balance($user);
            if ($balance['available'] < $balance['withdrawalThreshold']) {
                throw ValidationException::withMessages(['coins' => 'Вывод доступен при доступном балансе от 50 EnCoin.']);
            }
            if ($coins > $balance['available']) {
                throw ValidationException::withMessages(['coins' => 'Недостаточно доступных монет.']);
            }
            $request = MonetizationRequest::query()->create([
                'user_id' => $user->id, 'coins' => $coins, 'kopecks_per_coin' => $this->rate()->kopecks_per_coin,
                'client_request_id' => $clientId,
            ]);

            return ['request' => $request, 'created' => true];
        });
    }

    public function process(MonetizationRequest $request, User $admin): MonetizationRequest
    {
        return DB::transaction(function () use ($request, $admin): MonetizationRequest {
            User::query()->whereKey($request->user_id)->lockForUpdate()->firstOrFail();
            $locked = MonetizationRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->processed_at !== null) {
                return $locked;
            }
            $entry = EnCoinEntry::query()->create([
                'user_id' => $locked->user_id, 'source_key' => 'withdrawal:'.$locked->id,
                'amount' => -$locked->coins, 'kopecks_per_coin' => $locked->kopecks_per_coin, 'reason' => 'withdrawal',
            ]);
            $locked->update(['processed_at' => now(), 'processed_by' => $admin->id]);
            $this->notifyBalanceChanged(User::query()->findOrFail($locked->user_id), $entry, $locked->id);

            return $locked;
        });
    }

    private function notifyBalanceChanged(User $user, EnCoinEntry $entry, ?int $requestId = null): void
    {
        $balance = (int) $user->enCoinEntries()->sum('amount');
        $user->notify(new EnCoinBalanceChanged($entry, $balance, $requestId));
    }
}
