<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Enums\UserRole;
use App\Models\EnCoinEntry;
use App\Models\EnCoinRate;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Word;
use App\Notifications\DeliverStoredNotificationPush;
use App\Services\Auth\AuthTokenService;
use App\Services\EnCoinService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnCoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-09-18T21:01:00Z'));
    }

    public function test_new_balance_is_zero_and_only_current_users_data_is_returned(): void
    {
        $this->getJson('/api/v1/balance')->assertUnauthorized();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->credit($other, 60);
        $this->login($user);
        $this->getJson('/api/v1/balance')->assertOk()
            ->assertJsonPath('balance', 0)->assertJsonPath('reserved', 0)
            ->assertJsonPath('available', 0)->assertJsonPath('rublesPerCoin', 10)
            ->assertJsonPath('totalEarnedCoins', 0)->assertJsonPath('totalEarnedRubles', 0)->assertJsonCount(0, 'requests');
    }

    public function test_daily_reward_uses_moscow_midnight_and_retries_or_repeats_never_duplicate_it(): void
    {
        $user = User::factory()->create();
        $timely = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $late = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18'); // Keep week unfinished.
        $this->login($user);
        $payload = $this->completionPayload($timely, '2026-09-18T20:59:59Z');
        $this->postJson('/api/v1/exercises/complete', $payload)->assertCreated();
        $this->postJson('/api/v1/exercises/complete', $payload)->assertOk();
        $this->postJson('/api/v1/exercises/complete', [...$payload, 'attempt_id' => (string) Str::uuid()])->assertCreated();
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($late, '2026-09-18T21:00:00Z'))->assertCreated();
        $this->assertDatabaseHas('encoin_entries', ['exercise_id' => $timely->id, 'amount' => 2, 'kopecks_per_coin' => 1000]);
        $this->assertDatabaseHas('encoin_entries', ['exercise_id' => $late->id, 'amount' => 1]);
        $this->assertDatabaseCount('encoin_entries', 2);
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 3)->assertJsonPath('totalEarnedCoins', 3)->assertJsonPath('totalEarnedRubles', 30);
    }

    public function test_weekly_reward_is_five_and_self_study_earns_nothing(): void
    {
        $user = User::factory()->create();
        $weekly = $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18');
        $study = $this->exercise($user, ExerciseTypeCode::user, '2026-09-18');
        $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $this->login($user);
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($weekly))->assertCreated();
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($study))->assertCreated();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 5);
        $this->assertDatabaseCount('encoin_entries', 1);
        $notification = $user->notifications()->sole();
        $this->assertSame('weekly', $notification->data['reason']);
        $this->assertSame(5, $notification->data['amount']);
    }

    public function test_plural_reward_matches_daily_and_requires_only_english_result(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-18T20:00:00Z'));
        $user = User::factory()->create();
        $plural = $this->exercise($user, ExerciseTypeCode::plural, '2026-09-18');
        $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18');
        $this->login($user);
        $item = $plural->items->sole();

        $this->postJson('/api/v1/exercises/complete', [
            'exercise_id' => $plural->id,
            'attempt_id' => (string) Str::uuid(),
            'completed_at' => now()->toISOString(),
            'exercise_items_result' => [[
                'exercise_item_id' => $item->id,
                'lang_id' => 1,
                'errors_count' => 0,
                'hints_count' => 0,
                'variants' => [],
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('encoin_entries', [
            'exercise_id' => $plural->id,
            'amount' => 2,
            'reason' => 'daily',
        ]);
    }

    public function test_week_bonus_is_awarded_after_the_weekly_deadline_for_four_timely_daily_exercises_and_weekly(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-18T18:00:00Z'));
        $user = User::factory()->create();
        $dailyExercises = collect([
            $this->exercise($user, ExerciseTypeCode::daily, '2026-09-14'),
            $this->exercise($user, ExerciseTypeCode::daily, '2026-09-15'),
            $this->exercise($user, ExerciseTypeCode::daily, '2026-09-16'),
            $this->exercise($user, ExerciseTypeCode::plural, '2026-09-17'),
        ]);
        $weekly = $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18');
        $this->exercise($user, ExerciseTypeCode::user, '2026-09-18');
        $this->exercise(User::factory()->create(), ExerciseTypeCode::daily, '2026-09-18');
        $this->login($user);
        foreach ($dailyExercises as $exercise) {
            $this->postJson('/api/v1/exercises/complete', $this->completionPayload(
                $exercise,
                $exercise->dueDate->setTimezone('Europe/Moscow')->endOfDay()->toISOString(),
            ))->assertCreated();
        }
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($weekly))->assertCreated();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 13);

        $this->travelTo(CarbonImmutable::parse('2026-09-18T21:00:00Z'));
        $this->login($user);
        $this->artisan('encoin:award-weekly-bonuses')->assertSuccessful();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 18);
        $this->artisan('encoin:award-weekly-bonuses')->assertSuccessful();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 18);
        $extra = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($extra))->assertCreated();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 19)->assertJsonPath('totalEarnedCoins', 19);
        $this->assertSame(1, EnCoinEntry::query()->where('reason', 'weekly_bonus')->count());
        $bonus = $user->notifications()->get()->sole(fn ($item) => $item->data['reason'] === 'weekly_bonus');
        $this->assertSame('encoin.credited', $bonus->type);
        $this->assertSame(5, $bonus->data['amount']);
        $this->assertSame(18, $bonus->data['balance']);
        $this->assertCount(7, $user->notifications()->get());
    }

    public function test_week_bonus_is_not_awarded_for_an_incomplete_week(): void
    {
        $user = User::factory()->create();
        $daily = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-17');
        $weekly = $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18');
        $this->login($user);
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($daily, '2026-09-17T20:59:59Z'))->assertCreated();
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($weekly, '2026-09-18T20:59:59Z'))->assertCreated();
        $this->artisan('encoin:award-weekly-bonuses')->assertSuccessful();

        $this->assertDatabaseMissing('encoin_entries', ['user_id' => $user->id, 'reason' => 'weekly_bonus']);
    }

    public function test_week_bonus_is_not_awarded_when_a_daily_or_weekly_exercise_is_late(): void
    {
        foreach ([ExerciseTypeCode::daily, ExerciseTypeCode::weekly] as $lateType) {
            $user = User::factory()->create();
            $exercises = collect([
                $this->exercise($user, ExerciseTypeCode::daily, '2026-09-14'),
                $this->exercise($user, ExerciseTypeCode::daily, '2026-09-15'),
                $this->exercise($user, ExerciseTypeCode::daily, '2026-09-16'),
                $this->exercise($user, ExerciseTypeCode::plural, '2026-09-17'),
                $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18'),
            ]);
            $this->login($user);
            foreach ($exercises as $exercise) {
                $completedAt = $exercise->dueDate->setTimezone('Europe/Moscow')->endOfDay();
                if ((int) $exercise->type_id === $lateType->value) {
                    $completedAt = $completedAt->addSecond();
                }
                $this->postJson('/api/v1/exercises/complete', $this->completionPayload(
                    $exercise,
                    $completedAt->toISOString(),
                ))->assertCreated();
            }
        }

        $this->artisan('encoin:award-weekly-bonuses')->assertSuccessful();
        $this->assertSame(0, EnCoinEntry::query()->where('reason', 'weekly_bonus')->count());
    }

    public function test_partial_results_and_historical_repeats_do_not_earn_coins(): void
    {
        $user = User::factory()->create();
        $partial = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $historic = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $historic->completions()->create();
        $this->login($user);
        $payload = $this->completionPayload($partial);
        $payload['exercise_items_result'] = [$payload['exercise_items_result'][0]];
        $this->postJson('/api/v1/exercises/complete', $payload)->assertCreated();
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($historic))->assertCreated();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 0);
        $this->assertDatabaseCount('encoin_entries', 0);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_withdrawal_reserves_coins_and_idempotent_retries_keep_one_request(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 60);
        $this->login($user);
        $payload = ['coins' => 50, 'clientRequestId' => (string) Str::uuid()];
        $first = $this->postJson('/api/v1/balance/withdrawals', $payload)->assertCreated()
            ->assertJsonPath('item.amountRubles', 500)->assertJsonPath('item.isProcessed', false);
        $this->postJson('/api/v1/balance/withdrawals', $payload)->assertOk()->assertJsonPath('item.id', $first->json('item.id'));
        $this->postJson('/api/v1/balance/withdrawals', [...$payload, 'coins' => 49])->assertUnprocessable();
        $this->postJson('/api/v1/balance/withdrawals', ['coins' => 1, 'clientRequestId' => (string) Str::uuid()])->assertUnprocessable();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 60)
            ->assertJsonPath('reserved', 50)->assertJsonPath('available', 10)->assertJsonCount(1, 'requests');
        $this->assertDatabaseCount('monetization_requests', 1);
        $this->assertDatabaseCount('encoin_entries', 1);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_threshold_and_whole_coin_limits_are_enforced_on_server(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 49);
        $this->login($user);
        $this->postJson('/api/v1/balance/withdrawals', ['coins' => 1, 'clientRequestId' => (string) Str::uuid()])->assertUnprocessable();
        $this->credit($user, 1);
        foreach ([0, -1, 1.5, 51, 'invalid'] as $coins) {
            $this->postJson('/api/v1/balance/withdrawals', ['coins' => $coins, 'clientRequestId' => (string) Str::uuid()])->assertUnprocessable();
        }
        $this->postJson('/api/v1/balance/withdrawals', ['coins' => 1, 'clientRequestId' => (string) Str::uuid()])->assertCreated();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('available', 49);
    }

    public function test_processing_is_admin_only_and_deducts_reserved_coins_once(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 100);
        $service = app(EnCoinService::class);
        $first = $service->requestWithdrawal($user, 50, (string) Str::uuid())['request'];
        $second = $service->requestWithdrawal($user, 50, (string) Str::uuid())['request'];
        $this->login($user);
        $this->getJson('/api/v1/admin/monetization-requests')->assertForbidden();
        $url = '/api/v1/admin/monetization-requests/'.$first->id.'/processed';
        $this->putJson($url)->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::admin]);
        $this->login($admin);
        $this->getJson('/api/v1/admin/monetization-requests?status=all')->assertOk()
            ->assertJsonCount(2, 'items')->assertJsonPath('items.0.user.balance', 100);
        $this->putJson($url)->assertOk()->assertJsonPath('item.user.balance', 50)->assertJsonPath('item.user.reserved', 50);
        $this->putJson($url)->assertOk()->assertJsonPath('item.user.balance', 50);
        $this->putJson('/api/v1/admin/monetization-requests/'.$second->id.'/processed')->assertOk()->assertJsonPath('item.user.balance', 0);
        $this->assertSame($admin->id, $first->refresh()->processed_by);
        $this->getJson('/api/v1/admin/monetization-requests?status=pending')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson('/api/v1/admin/monetization-requests?status=processed')->assertOk()->assertJsonCount(2, 'items');
        $this->login($user);
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 0)->assertJsonPath('reserved', 0)->assertJsonPath('totalEarnedCoins', 100)->assertJsonPath('totalEarnedRubles', 1000);
        $this->assertSame(2, EnCoinEntry::query()->where('reason', 'withdrawal')->count());
        $notifications = $user->notifications()->orderBy('id')->get();
        $this->assertCount(2, $notifications);
        $this->assertSame('encoin.withdrawal.processed', $notifications[0]->type);
        $this->assertSame(-50, $notifications[0]->data['amount']);
        $this->assertSame(50, $notifications[0]->data['balance']);
        $this->assertSame($first->id, $notifications[0]->data['monetizationRequestId']);
        $this->assertStringContainsString('500,00 ₽ зачислены на карту', $notifications[0]->body);
        $this->assertSame(0, $notifications[1]->data['balance']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_rate_updates_keep_existing_request_and_lifetime_earnings_unchanged(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 60);
        $request = app(EnCoinService::class)->requestWithdrawal($user, 50, (string) Str::uuid())['request'];
        $admin = User::factory()->create(['role' => UserRole::admin]);
        $this->login($admin);
        $this->getJson('/api/v1/admin/encoin-rate')->assertOk()->assertJsonPath('rublesPerCoin', 10);
        $this->putJson('/api/v1/admin/encoin-rate', ['rublesPerCoin' => '12.50'])->assertOk()->assertJsonPath('rublesPerCoin', 12.5);
        $this->assertSame($admin->id, EnCoinRate::query()->findOrFail(1)->updated_by);
        $this->getJson('/api/v1/admin/monetization-requests')->assertOk()
            ->assertJsonPath('items.0.amountRubles', 500)->assertJsonPath('items.0.rublesPerCoin', 10);
        $this->putJson('/api/v1/admin/monetization-requests/'.$request->id.'/processed')->assertOk();
        $this->assertSame(500, $user->notifications()->sole()->data['amountRubles']);
        $daily = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18');
        $this->login($user);
        $this->postJson('/api/v1/exercises/complete', $this->completionPayload($daily, '2026-09-18T20:00:00Z'))->assertCreated();
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 12)
            ->assertJsonPath('rublesPerCoin', 12.5)->assertJsonPath('totalEarnedCoins', 62)->assertJsonPath('totalEarnedRubles', 625);
        $this->assertDatabaseHas('encoin_entries', ['exercise_id' => $daily->id, 'kopecks_per_coin' => 1250, 'amount' => 2]);
    }

    public function test_rate_is_protected_and_accepts_only_positive_two_decimal_amounts(): void
    {
        $this->login(User::factory()->create());
        $this->getJson('/api/v1/admin/encoin-rate')->assertForbidden();
        $this->putJson('/api/v1/admin/encoin-rate', ['rublesPerCoin' => 20])->assertForbidden();
        $this->login(User::factory()->create(['role' => UserRole::admin]));
        foreach ([0, -1, '1.234', '1e2', 100001, 'bad'] as $value) {
            $this->putJson('/api/v1/admin/encoin-rate', ['rublesPerCoin' => $value])->assertUnprocessable();
        }
        $this->getJson('/api/v1/admin/encoin-rate')->assertOk()->assertJsonPath('rublesPerCoin', 10);
        $this->putJson('/api/v1/admin/encoin-rate', ['rublesPerCoin' => '0.01'])->assertOk()->assertJsonPath('rublesPerCoin', 0.01);
    }

    public function test_reward_notification_is_journaled_once_and_push_is_queued_after_commit_for_enabled_device(): void
    {
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-18T10:00:00Z'));
        $user = User::factory()->create();
        $device = $user->devices()->create([
            'installation_id' => (string) Str::uuid(), 'push_token' => Str::random(100),
            'platform' => 'android', 'notifications_enabled' => true, 'last_seen_at' => now(),
        ]);
        $user->devices()->create([
            'installation_id' => (string) Str::uuid(), 'push_token' => Str::random(100),
            'platform' => 'android', 'notifications_enabled' => false, 'last_seen_at' => now(),
        ]);
        $daily = $this->exercise($user, ExerciseTypeCode::daily, '2026-09-18');
        $this->exercise($user, ExerciseTypeCode::weekly, '2026-09-18');
        $this->login($user);
        $payload = $this->completionPayload($daily);
        $this->postJson('/api/v1/exercises/complete', $payload)->assertCreated();
        $this->postJson('/api/v1/exercises/complete', $payload)->assertOk();
        $this->postJson('/api/v1/exercises/complete', [...$payload, 'attempt_id' => (string) Str::uuid()])->assertCreated();
        $stored = $user->notifications()->sole();
        $this->assertSame('encoin.credited', $stored->type);
        $this->assertSame('/balance', $stored->data['route']);
        $this->assertSame(2, $stored->data['amount']);
        $this->assertSame(2, $stored->data['balance']);
        $this->assertSame($daily->id, $stored->data['exerciseId']);
        $this->getJson('/api/v1/notifications?afterSequence=0')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.data.route', '/balance');
        Queue::assertPushed(SendQueuedNotifications::class, 1);
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job): bool => $job->notification instanceof DeliverStoredNotificationPush
            && $job->notification->afterCommit === true
            && $job->notification->notificationId === $stored->id
            && $job->notifiables->contains($device));
    }

    public function test_rolled_back_withdrawal_does_not_leave_a_balance_notification(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 60);
        $service = app(EnCoinService::class);
        $request = $service->requestWithdrawal($user, 50, (string) Str::uuid())['request'];
        $admin = User::factory()->create(['role' => UserRole::admin]);
        DB::beginTransaction();
        try {
            $service->process($request, $admin);
            $this->assertSame(1, $user->notifications()->count());
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, $user->notifications()->count());
        $this->assertNull($request->refresh()->processed_at);
        $this->assertSame(60, $service->balance($user)['balance']);
    }

    private function exercise(User $user, ExerciseTypeCode $type, string $date): Exercise
    {
        $exercise = $user->exercises()->create(['type_id' => $type->value, 'dueDate' => $date]);
        $word = Word::query()->create(['ru' => 'дом', 'en' => 'home', 'grade' => 1]);
        $exercise->items()->create(['word_id' => $word->id]);

        return $exercise;
    }

    private function completionPayload(Exercise $exercise, ?string $time = null): array
    {
        $results = [];
        foreach ($exercise->items as $item) {
            foreach ([1, 2] as $lang) {
                $results[] = ['exercise_item_id' => $item->id, 'lang_id' => $lang, 'errors_count' => 0, 'hints_count' => 0, 'variants' => []];
            }
        }

        return ['exercise_id' => $exercise->id, 'attempt_id' => (string) Str::uuid(), 'completed_at' => $time ?? now()->toISOString(), 'exercise_items_result' => $results];
    }

    private function credit(User $user, int $amount): void
    {
        EnCoinEntry::query()->create(['user_id' => $user->id, 'amount' => $amount, 'kopecks_per_coin' => 1000, 'reason' => 'daily', 'source_key' => (string) Str::uuid()]);
    }

    private function login(User $user): void
    {
        $this->withToken(app(AuthTokenService::class)->issue($user)['accessToken']);
    }
}
