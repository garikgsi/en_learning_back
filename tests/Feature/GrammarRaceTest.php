<?php

namespace Tests\Feature;

use App\Enums\GrammarRaceGameCode;
use App\Models\EnCoinEntry;
use App\Models\GrammarRaceProfile;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use App\Services\GrammarRace\Data\PersonalPronounLexicon;
use App\Services\GrammarRace\GrammarRaceDifficultyService;
use App\Services\GrammarRace\PersonalPronounTaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GrammarRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-22T10:00:00Z'));
    }

    public function test_generator_has_large_curated_lexicon_and_balances_pronouns(): void
    {
        $this->assertGreaterThanOrEqual(50, count(PersonalPronounLexicon::maleNames()));
        $this->assertGreaterThanOrEqual(50, count(PersonalPronounLexicon::femaleNames()));
        $this->assertGreaterThanOrEqual(50, count(PersonalPronounLexicon::surnames()));
        $this->assertGreaterThanOrEqual(100, count(PersonalPronounLexicon::objects()));

        $tasks = app(PersonalPronounTaskGenerator::class)->generate(1, 50);

        foreach (array_chunk($tasks, 5) as $cycle) {
            $this->assertSame(
                ['he', 'it', 'she', 'they', 'we'],
                collect($cycle)->pluck('correct_answer')->sort()->values()->all(),
            );
        }

        foreach ($tasks as $index => $task) {
            $this->assertNotNull($task['translation']);
            $this->assertGreaterThanOrEqual(3000, $task['bot_delay_ms']);
            $this->assertLessThanOrEqual(10000, $task['bot_delay_ms']);
            if ($index > 0) {
                $this->assertNotSame($tasks[$index - 1]['correct_answer'], $task['correct_answer']);
            }
        }
    }

    public function test_sentence_levels_use_full_sentences_and_have_got(): void
    {
        $tasks = app(PersonalPronounTaskGenerator::class)->generate(20, 50);

        foreach ($tasks as $task) {
            $this->assertStringContainsString('___', $task['prompt']);
            $this->assertNull($task['translation']);
            $this->assertStringNotContainsString(' have a ', strtolower($task['prompt']));
            $this->assertGreaterThanOrEqual(3000, $task['bot_delay_ms']);
            $this->assertLessThanOrEqual(5500, $task['bot_delay_ms']);
        }
    }

    public function test_twenty_levels_have_phrase_and_sentence_grace_windows(): void
    {
        $levels = config('grammar_race.levels');

        $this->assertCount(20, $levels);
        foreach (range(1, 10) as $level) {
            $this->assertSame('phrase', $levels[$level]['mode']);
            $this->assertSame(5000, $levels[$level]['answer_grace_ms']);
        }
        foreach (range(11, 20) as $level) {
            $this->assertSame('sentence', $levels[$level]['mode']);
            $this->assertSame(10000, $levels[$level]['answer_grace_ms']);
        }
        $this->assertSame(25, $levels[1]['bot_error_percent']);
        $this->assertSame(5, $levels[10]['bot_error_percent']);
        $this->assertSame(10000, $levels[11]['bot_max_delay_ms']);
        $this->assertSame(5500, $levels[20]['bot_max_delay_ms']);
    }

    public function test_first_attempt_is_free_next_two_cost_one_and_fourth_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 2);
        $this->login($user);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $start = $this->startRace()
                ->assertCreated()
                ->assertJsonPath('item.attemptNumber', $attempt)
                ->assertJsonPath('item.entryCost', $attempt === 1 ? 0 : 1);
            $this->abandonRace($start->json('item.id'))->assertCreated();
        }

        $this->startRace()
            ->assertConflict()
            ->assertJsonPath('code', 'DAILY_ATTEMPT_LIMIT_REACHED');
        $this->assertSame(2, EnCoinEntry::query()->where('reason', 'grammar_race_entry')->count());
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 0);
    }

    public function test_start_is_idempotent_and_a_lost_response_cannot_charge_twice(): void
    {
        $user = User::factory()->create();
        $this->credit($user, 1);
        $this->login($user);

        $first = $this->startRace()->assertCreated();
        $this->abandonRace($first->json('item.id'))->assertCreated();
        $requestId = (string) Str::uuid();
        $paid = $this->startRace($requestId)->assertCreated();
        $retry = $this->startRace($requestId)->assertOk();

        $retry->assertJsonPath('item.id', $paid->json('item.id'));
        $this->assertSame(1, EnCoinEntry::query()->where('reason', 'grammar_race_entry')->count());
    }

    public function test_student_win_is_validated_and_rewarded_once(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $start = $this->startRace()->assertCreated()->assertJsonCount(50, 'item.tasks');
        $resultId = (string) Str::uuid();
        $payload = $this->winningPayload($start->json('item'), $resultId);
        $url = '/api/v1/grammar-race-sessions/'.$start->json('item.id').'/complete';

        $this->postJson($url, $payload)
            ->assertCreated()
            ->assertJsonPath('item.status', 'student_won')
            ->assertJsonPath('item.score.student', 5)
            ->assertJsonPath('item.score.computer', 0);
        $this->postJson($url, $payload)->assertOk();

        $this->assertSame(1, EnCoinEntry::query()->where('reason', 'grammar_race_reward')->count());
        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 2);
        $this->assertDatabaseHas('grammar_race_profiles', [
            'user_id' => $user->id,
            'games_played' => 1,
            'games_won' => 1,
            'current_level' => 1,
        ]);
    }

    public function test_completed_offline_match_has_no_synchronization_expiry(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $start = $this->startRace()->assertCreated();
        $completedAt = now()->addMinute()->toISOString();

        $this->travelTo(now()->addDays(90));
        $this->login($user);
        $payload = $this->winningPayload($start->json('item'), (string) Str::uuid(), $completedAt);

        $this->postJson(
            '/api/v1/grammar-race-sessions/'.$start->json('item.id').'/complete',
            $payload,
        )->assertCreated()->assertJsonPath('item.status', 'student_won');

        $this->getJson('/api/v1/balance')->assertOk()->assertJsonPath('balance', 2);
    }

    public function test_five_games_with_at_least_seventy_percent_wins_raise_one_level(): void
    {
        $user = User::factory()->create();

        for ($game = 0; $game < 5; $game++) {
            $this->login($user);
            $start = $this->startRace()->assertCreated();
            $this->postJson(
                '/api/v1/grammar-race-sessions/'.$start->json('item.id').'/complete',
                $this->winningPayload($start->json('item'), (string) Str::uuid()),
            )->assertCreated();
            $this->travelTo(now()->addDay());
        }

        $profile = GrammarRaceProfile::query()->where('user_id', $user->id)->sole();
        $this->assertSame(2, $profile->current_level);
        $this->assertSame(2, $profile->max_level);
        $this->assertSame(0, $profile->games_at_level);
        $this->assertSame(0, $profile->wins_at_level);
    }

    public function test_level_requires_at_least_seventy_percent_wins_and_never_decreases(): void
    {
        $service = app(GrammarRaceDifficultyService::class);
        $gameCode = GrammarRaceGameCode::personalPronouns;
        $successful = User::factory()->create();
        $unsuccessful = User::factory()->create();

        foreach ([true, true, true, true, false] as $won) {
            $service->recordResult($successful, $gameCode, $won);
        }
        foreach ([true, true, true, false, false] as $won) {
            $service->recordResult($unsuccessful, $gameCode, $won);
        }

        $this->assertSame(2, $service->profile($successful, $gameCode)->current_level);
        $profile = $service->profile($unsuccessful, $gameCode);
        $this->assertSame(1, $profile->current_level);

        foreach (range(1, 10) as $_) {
            $service->recordResult($unsuccessful, $gameCode, false);
        }
        $this->assertSame(1, $profile->refresh()->current_level);
        $this->assertSame(1, $profile->max_level);
    }

    public function test_server_rejects_changed_idempotent_result_and_impossible_task_order(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $start = $this->startRace()->assertCreated();
        $payload = $this->winningPayload($start->json('item'), (string) Str::uuid());
        $url = '/api/v1/grammar-race-sessions/'.$start->json('item.id').'/complete';

        $payload['rounds'][0]['taskPosition'] = 2;
        $this->postJson($url, $payload)->assertUnprocessable();
        $payload = $this->winningPayload($start->json('item'), $payload['clientResultId']);
        $this->postJson($url, $payload)->assertCreated();
        $payload['rounds'][0]['playerAnswerMs'] = 1;
        $this->postJson($url, $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
    }

    private function login(User $user): void
    {
        $this->withToken(app(AuthTokenService::class)->issue($user)['accessToken']);
    }

    private function credit(User $user, int $amount): void
    {
        EnCoinEntry::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'kopecks_per_coin' => 1000,
            'reason' => 'test',
            'source_key' => 'test:'.Str::uuid(),
        ]);
    }

    private function startRace(?string $requestId = null)
    {
        return $this->postJson('/api/v1/grammar-race-games/personal_pronouns/sessions', [
            'clientRequestId' => $requestId ?? (string) Str::uuid(),
        ]);
    }

    private function abandonRace(string $sessionId)
    {
        return $this->postJson("/api/v1/grammar-race-sessions/{$sessionId}/abandon", [
            'clientResultId' => (string) Str::uuid(),
            'abandonedAt' => now()->toISOString(),
        ]);
    }

    /** @param array<string, mixed> $session */
    private function winningPayload(array $session, string $resultId, ?string $completedAt = null): array
    {
        return [
            'clientResultId' => $resultId,
            'completedAt' => $completedAt ?? now()->addMinute()->toISOString(),
            'rounds' => collect($session['tasks'])->take(5)->map(fn (array $task): array => [
                'taskPosition' => $task['position'],
                'playerAnswer' => $task['correctAnswer'],
                'playerAnswerMs' => 0,
            ])->all(),
        ];
    }
}
