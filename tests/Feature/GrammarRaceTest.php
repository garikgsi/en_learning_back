<?php

namespace Tests\Feature;

use App\Enums\GrammarRaceGameCode;
use App\Models\EnCoinEntry;
use App\Models\GrammarRaceProfile;
use App\Models\GrammarRaceTask;
use App\Models\User;
use App\Notifications\DeliverStoredNotificationPush;
use App\Services\Auth\AuthTokenService;
use App\Services\GrammarRace\ArticleTaskGenerator;
use App\Services\GrammarRace\Data\GeneratedGrammarRaceTask;
use App\Services\GrammarRace\Data\PersonalPronounLexicon;
use App\Services\GrammarRace\GrammarRaceDifficultyService;
use App\Services\GrammarRace\PersonalPronounTaskGenerator;
use App\Services\GrammarRace\PossessivePronounTaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
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

        $levels = config('grammar_race.games.personal_pronouns.levels');
        $tasks = app(PersonalPronounTaskGenerator::class)->generate($levels[1], 50);

        foreach (array_chunk($tasks, 5) as $cycle) {
            $this->assertSame(
                ['he', 'it', 'she', 'they', 'we'],
                collect($cycle)->pluck('correctAnswer')->sort()->values()->all(),
            );
        }

        foreach ($tasks as $index => $task) {
            $this->assertNotNull($task->payload['translation']);
            $this->assertSame(
                "{$task->payload['text']} → {$task->correctAnswer}",
                $task->payload['feedback']['correctText'],
            );
            $this->assertStringContainsString(' → ', $task->payload['feedback']['translation']);
            $this->assertNotEmpty($task->payload['feedback']['explanation']);
            $this->assertGreaterThanOrEqual(3000, $task->botDelayMs);
            $this->assertLessThanOrEqual(10000, $task->botDelayMs);
            if ($index > 0) {
                $this->assertNotSame($tasks[$index - 1]->correctAnswer, $task->correctAnswer);
            }
        }

        $expectedExplanations = [
            'he' => 'Вместо имени одного мальчика или мужчины используем «он» — he.',
            'she' => 'Вместо имени одной девочки или женщины используем «она» — she.',
            'it' => 'Вместо названия одного предмета, животного или явления используем «оно» — it.',
            'we' => 'Когда говорим «я и ещё кто-то», вместе это «мы», поэтому правильный ответ — we.',
            'they' => 'Когда говорим о нескольких людях или предметах без себя, это «они», поэтому правильный ответ — they.',
        ];
        foreach ($expectedExplanations as $answer => $explanation) {
            $task = collect($tasks)->firstWhere('correctAnswer', $answer);
            $this->assertNotNull($task);
            $this->assertSame($explanation, $task->payload['feedback']['explanation']);
        }
    }

    public function test_sentence_levels_use_full_sentences_and_have_got(): void
    {
        $levels = config('grammar_race.games.personal_pronouns.levels');
        $tasks = app(PersonalPronounTaskGenerator::class)->generate($levels[20], 50);

        foreach ($tasks as $task) {
            $this->assertStringContainsString('___', $task->payload['text']);
            $this->assertStringNotContainsString('___', $task->payload['feedback']['correctText']);
            $this->assertNotEmpty($task->payload['feedback']['translation']);
            $this->assertNotEmpty($task->payload['feedback']['explanation']);
            $this->assertNull($task->payload['translation']);
            $this->assertStringNotContainsString(' have a ', strtolower($task->payload['text']));
            $this->assertGreaterThanOrEqual(24000, $task->botDelayMs);
            $this->assertLessThanOrEqual(31500, $task->botDelayMs);
        }
    }

    public function test_twenty_levels_have_phrase_and_sentence_grace_windows(): void
    {
        $levels = config('grammar_race.games.personal_pronouns.levels');

        $this->assertCount(20, $levels);
        foreach (range(1, 10) as $level) {
            $this->assertSame('phrase', $levels[$level]['mode']);
            $this->assertSame(5000, $levels[$level]['answer_grace_ms']);
        }
        foreach (range(11, 20) as $level) {
            $this->assertSame('sentence', $levels[$level]['mode']);
            $this->assertSame(10000, $levels[$level]['answer_grace_ms']);
            $this->assertSame(24000, $levels[$level]['bot_min_delay_ms']);
        }
        $this->assertSame(25, $levels[1]['bot_error_percent']);
        $this->assertSame(5, $levels[10]['bot_error_percent']);
        $this->assertSame(45000, $levels[11]['bot_max_delay_ms']);
        $this->assertSame(31500, $levels[20]['bot_max_delay_ms']);
    }

    public function test_possessive_pronouns_have_five_sentence_levels_and_balanced_tasks(): void
    {
        $levels = config('grammar_race.games.possessive_pronouns.levels');

        $this->assertCount(5, $levels);
        $this->assertSame([25, 20, 15, 10, 5], collect($levels)->pluck('bot_error_percent')->all());
        $this->assertSame([45000, 41625, 38250, 34875, 31500], collect($levels)->pluck('bot_max_delay_ms')->all());
        $this->assertSame([24000], collect($levels)->pluck('bot_min_delay_ms')->unique()->values()->all());
        $this->assertSame(['sentence'], collect($levels)->pluck('mode')->unique()->values()->all());

        $tasks = app(PossessivePronounTaskGenerator::class)->generate($levels[5], 49);
        $this->assertCount(49, $tasks);
        $this->assertSame(
            ['her', 'his', 'its', 'my', 'our', 'their', 'your'],
            collect($tasks)->pluck('correctAnswer')->unique()->sort()->values()->all(),
        );

        foreach ($tasks as $task) {
            $this->assertSame('single_choice', $task->type);
            $this->assertStringContainsString('___', $task->payload['text']);
            $this->assertMatchesRegularExpression('/___\s+\S/u', $task->payload['text']);
            $this->assertStringNotContainsString('___', $task->payload['feedback']['correctText']);
            $this->assertNotEmpty($task->payload['feedback']['translation']);
            $this->assertNull($task->payload['translation']);
            $this->assertCount(7, $task->options);
            $this->assertGreaterThanOrEqual(24000, $task->botDelayMs);
            $this->assertLessThanOrEqual(31500, $task->botDelayMs);
        }

        $expectedExplanations = [
            'my' => 'Предмет принадлежит мне (I), поэтому используем my.',
            'your' => 'Предмет принадлежит тебе или вам (you), поэтому используем your.',
            'his' => 'Собственник — мальчик или мужчина (he), поэтому используем his.',
            'her' => 'Собственник — девочка или женщина (she), поэтому используем her.',
            'its' => 'Собственник — животное или предмет (it), поэтому используем its.',
            'our' => 'Предмет принадлежит нам (we), поэтому используем our.',
            'their' => 'Предмет принадлежит нескольким людям, животным или предметам (they), поэтому используем their.',
        ];
        foreach ($expectedExplanations as $answer => $explanation) {
            $task = collect($tasks)->firstWhere('correctAnswer', $answer);
            $this->assertNotNull($task);
            $this->assertSame($explanation, $task->payload['feedback']['explanation']);
        }
    }

    public function test_possessive_pronouns_require_fifth_grade_and_have_independent_free_attempt(): void
    {
        $fourthGrade = User::factory()->create();
        $fourthGrade->info()->create(['first_grade_year' => now()->year - 4]);
        $this->login($fourthGrade);
        $this->getJson('/api/v1/grammar-race-games/possessive_pronouns/status')
            ->assertOk()
            ->assertJsonPath('minGrade', 5)
            ->assertJsonPath('maxLevel', 5)
            ->assertJsonPath('isAvailable', false);
        $this->startRace(gameCode: 'possessive_pronouns')->assertForbidden();

        $fifthGrade = User::factory()->create();
        $fifthGrade->info()->create(['first_grade_year' => now()->year - 5]);
        $this->login($fifthGrade);
        $personal = $this->startRace()->assertCreated()->assertJsonPath('item.attemptNumber', 1);
        $this->abandonRace($personal->json('item.id'))->assertCreated();
        $possessive = $this->startRace(gameCode: 'possessive_pronouns')->assertCreated()
            ->assertJsonPath('item.gameCode', 'possessive_pronouns')
            ->assertJsonPath('item.attemptNumber', 1)
            ->assertJsonPath('item.entryCost', 0)
            ->assertJsonPath('item.difficulty.botMinDelayMs', 33600)
            ->assertJsonPath('item.difficulty.botMaxDelayMs', 63000)
            ->assertJsonPath('item.tasks.0.type', 'single_choice');
        $this->assertCount(7, $possessive->json('item.tasks.0.options'));
        $this->postJson(
            '/api/v1/grammar-race-sessions/'.$possessive->json('item.id').'/complete',
            $this->winningPayload($possessive->json('item'), (string) Str::uuid()),
        )->assertCreated()->assertJsonPath('item.status', 'student_won');
        $this->assertDatabaseHas('grammar_race_profiles', [
            'user_id' => $fifthGrade->id,
            'game_code' => 'possessive_pronouns',
            'games_won' => 1,
        ]);
    }

    public function test_articles_have_ten_progressive_levels_and_child_friendly_feedback(): void
    {
        $levels = config('grammar_race.games.articles.levels');

        $this->assertCount(10, $levels);
        $this->assertSame(array_fill(0, 5, 'phrase'), collect($levels)->take(5)->pluck('mode')->all());
        $this->assertSame(array_fill(0, 5, 'sentence'), collect($levels)->skip(5)->pluck('mode')->all());
        $this->assertSame([25, 23, 21, 19, 17, 15, 13, 11, 8, 5], collect($levels)->pluck('bot_error_percent')->all());
        $this->assertSame(
            [10000, 9500, 9000, 8500, 8000, 37500, 36000, 34500, 33000, 31500],
            collect($levels)->pluck('bot_max_delay_ms')->all(),
        );
        $this->assertSame(
            [3000, 3000, 3000, 3000, 3000, 24000, 24000, 24000, 24000, 24000],
            collect($levels)->pluck('bot_min_delay_ms')->all(),
        );

        $firstLevelTasks = app(ArticleTaskGenerator::class)->generate($levels[1], 50);
        $this->assertSame(['a', 'an'], collect($firstLevelTasks)->pluck('correctAnswer')->unique()->sort()->values()->all());
        foreach ($firstLevelTasks as $task) {
            $this->assertCount(4, $task->options);
            $this->assertSame(['a', 'an', 'the', 'none'], collect($task->options)->pluck('id')->all());
            $this->assertSame('Выберите правильный артикль', $task->payload['instruction']);
            $this->assertMatchesRegularExpression('/___\s+\S/u', $task->payload['text']);
            $this->assertNotEmpty($task->payload['translation']);
            $this->assertNotEmpty($task->payload['feedback']['correctText']);
            $this->assertNotEmpty($task->payload['feedback']['translation']);
            $this->assertNotEmpty($task->payload['feedback']['explanation']);
        }

        $secondLevelTasks = app(ArticleTaskGenerator::class)->generate($levels[2], 10);
        $this->assertNull($secondLevelTasks[0]->payload['translation']);

        $translationTasks = app(ArticleTaskGenerator::class)->generate($levels[1], 160);
        $this->assertNotContains(
            'Один предмет',
            collect($translationTasks)->pluck('payload.translation')->all(),
        );
        $auntTask = collect($translationTasks)
            ->first(fn (GeneratedGrammarRaceTask $task): bool => $task->payload['text'] === '___ aunt');
        $this->assertNotNull($auntTask);
        $this->assertSame('Тётя', $auntTask->payload['translation']);

        $fifthLevelTasks = app(ArticleTaskGenerator::class)->generate($levels[5], 50);
        $this->assertSame(
            ['a', 'an', 'none', 'the'],
            collect($fifthLevelTasks)->pluck('correctAnswer')->unique()->sort()->values()->all(),
        );

        $eighthLevelTasks = app(ArticleTaskGenerator::class)->generate($levels[8], 50);
        $zeroArticlePrompts = collect($eighthLevelTasks)
            ->where('correctAnswer', 'none')
            ->pluck('payload.text');
        $this->assertTrue($zeroArticlePrompts->contains(fn (string $prompt): bool => str_starts_with($prompt, '___ ')));
        $this->assertFalse($zeroArticlePrompts->contains('___ Cats like milk.'));
        $this->assertFalse($zeroArticlePrompts->contains('___ Water is important.'));
    }

    public function test_articles_require_third_grade_and_keep_attempts_separate(): void
    {
        $secondGrade = User::factory()->create();
        $secondGrade->info()->create(['first_grade_year' => now()->year - 2]);
        $this->login($secondGrade);
        $this->getJson('/api/v1/grammar-race-games/articles/status')
            ->assertOk()
            ->assertJsonPath('minGrade', 3)
            ->assertJsonPath('maxLevel', 10)
            ->assertJsonPath('isAvailable', false);
        $this->startRace(gameCode: 'articles')->assertForbidden();

        $thirdGrade = User::factory()->create();
        $thirdGrade->info()->create(['first_grade_year' => now()->year - 3]);
        $this->login($thirdGrade);
        $personal = $this->startRace()->assertCreated();
        $this->abandonRace($personal->json('item.id'))->assertCreated();

        $articles = $this->startRace(gameCode: 'articles')->assertCreated()
            ->assertJsonPath('item.gameCode', 'articles')
            ->assertJsonPath('item.attemptNumber', 1)
            ->assertJsonPath('item.entryCost', 0)
            ->assertJsonPath('item.difficulty.level', 1)
            ->assertJsonPath('item.difficulty.mode', 'phrase')
            ->assertJsonPath('item.tasks.0.payload.instruction', 'Выберите правильный артикль');
        $this->assertCount(4, $articles->json('item.tasks.0.options'));
        $this->assertNotEmpty($articles->json('item.tasks.0.payload.translation'));
    }

    public function test_achievements_list_every_game_and_normalize_medals_to_five_colours(): void
    {
        $user = User::factory()->create();
        $user->info()->create(['first_grade_year' => now()->year - 5]);
        $this->login($user);

        $this->getJson('/api/v1/grammar-race-achievements')
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('levelUp.minimumGames', 5)
            ->assertJsonPath('levelUp.minimumWinRatePercent', 70)
            ->assertJsonPath('items.0.gameCode', 'personal_pronouns')
            ->assertJsonPath('items.0.gameTitle', 'Гонка местоимений')
            ->assertJsonPath('items.0.rankTitle', 'Гонщик местоимений')
            ->assertJsonPath('items.0.currentLevel', 1)
            ->assertJsonPath('items.0.maxLevel', 20)
            ->assertJsonPath('items.0.medalTier', 1)
            ->assertJsonPath('items.0.isAvailable', true)
            ->assertJsonPath('items.1.gameCode', 'possessive_pronouns')
            ->assertJsonPath('items.1.rankTitle', 'Знаток притяжательных местоимений')
            ->assertJsonPath('items.1.maxLevel', 5)
            ->assertJsonPath('items.1.medalTier', 1)
            ->assertJsonPath('items.1.isAvailable', true)
            ->assertJsonPath('items.2.gameCode', 'articles')
            ->assertJsonPath('items.2.gameTitle', 'Гонка артиклей')
            ->assertJsonPath('items.2.rankTitle', 'Знаток артиклей')
            ->assertJsonPath('items.2.maxLevel', 10)
            ->assertJsonPath('items.2.isAvailable', true);

        GrammarRaceProfile::query()->create([
            'user_id' => $user->id,
            'game_code' => GrammarRaceGameCode::possessivePronouns,
            'current_level' => 5,
            'max_level' => 5,
            'games_played' => 20,
            'games_won' => 20,
        ]);

        $this->getJson('/api/v1/grammar-race-achievements')
            ->assertOk()
            ->assertJsonPath('items.1.currentLevel', 5)
            ->assertJsonPath('items.1.medalTier', 5)
            ->assertJsonPath('items.1.isMaxLevel', true)
            ->assertJsonPath('items.1.progressPercent', 100);
    }

    public function test_game_requires_second_grade_and_scales_only_bot_time(): void
    {
        $firstGrade = User::factory()->create();
        $firstGrade->info()->create(['first_grade_year' => now()->year - 1]);
        $this->login($firstGrade);
        $this->getJson('/api/v1/grammar-race-games/personal_pronouns/status')
            ->assertOk()
            ->assertJsonPath('minGrade', 2)
            ->assertJsonPath('isAvailable', false);
        $this->startRace()
            ->assertForbidden()
            ->assertJsonPath('code', 'GRADE_NOT_AVAILABLE');

        $fourthGrade = User::factory()->create();
        $fourthGrade->info()->create(['first_grade_year' => now()->year - 4]);
        $this->login($fourthGrade);
        $start = $this->startRace()->assertCreated()
            ->assertJsonPath('item.difficulty.botErrorPercent', 25)
            ->assertJsonPath('item.difficulty.botMinDelayMs', 4800)
            ->assertJsonPath('item.difficulty.botMaxDelayMs', 16000);

        foreach ($start->json('item.tasks') as $task) {
            $this->assertGreaterThanOrEqual(4800, $task['botDelayMs']);
            $this->assertLessThanOrEqual(16000, $task['botDelayMs']);
        }

        $adult = User::factory()->create();
        $adult->info()->create(['first_grade_year' => now()->year - 36]);
        $this->login($adult);
        $adultStart = $this->startRace(playMode: 'training')->assertCreated()
            ->assertJsonPath('item.difficulty.botMinDelayMs', 3000)
            ->assertJsonPath('item.difficulty.botMaxDelayMs', 10000);

        foreach ($adultStart->json('item.tasks') as $task) {
            $this->assertGreaterThanOrEqual(3000, $task['botDelayMs']);
            $this->assertLessThanOrEqual(10000, $task['botDelayMs']);
        }
    }

    public function test_training_is_unlimited_free_and_does_not_change_progress_or_reward(): void
    {
        $user = User::factory()->create();
        $this->login($user);

        $start = $this->startRace(playMode: 'training')
            ->assertCreated()
            ->assertJsonPath('item.playMode', 'training')
            ->assertJsonPath('item.attemptNumber', null)
            ->assertJsonPath('item.entryCost', 0)
            ->assertJsonPath('item.winReward', 0);
        $this->postJson(
            '/api/v1/grammar-race-sessions/'.$start->json('item.id').'/complete',
            $this->winningPayload($start->json('item'), (string) Str::uuid()),
        )->assertCreated();

        foreach (range(1, 4) as $_) {
            $training = $this->startRace(playMode: 'training')->assertCreated();
            $this->abandonRace($training->json('item.id'))->assertCreated();
        }

        $this->getJson('/api/v1/grammar-race-games/personal_pronouns/status')
            ->assertOk()
            ->assertJsonPath('attemptsUsed', 0)
            ->assertJsonPath('attemptsRemaining', 3);
        $this->assertDatabaseMissing('encoin_entries', ['user_id' => $user->id]);
        $this->assertDatabaseHas('grammar_race_profiles', [
            'user_id' => $user->id,
            'games_played' => 0,
            'games_won' => 0,
        ]);
    }

    public function test_unsubmitted_training_does_not_block_a_competitive_game(): void
    {
        $user = User::factory()->create();
        $this->login($user);

        $training = $this->startRace(playMode: 'training')
            ->assertCreated()
            ->assertJsonPath('item.status', 'active');

        $this->getJson('/api/v1/grammar-race-games/personal_pronouns/status')
            ->assertOk()
            ->assertJsonPath('activeSession', null);

        $this->startRace()
            ->assertCreated()
            ->assertJsonPath('item.playMode', 'competitive');

        $this->assertDatabaseHas('grammar_race_sessions', [
            'id' => $training->json('item.id'),
            'status' => 'abandoned',
        ]);
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

    public function test_paid_attempt_is_journaled_without_push(): void
    {
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $user = User::factory()->create();
        $user->devices()->create([
            'installation_id' => (string) Str::uuid(),
            'push_token' => 'paid-attempt-token',
            'platform' => 'android',
            'notifications_enabled' => true,
            'last_seen_at' => now(),
        ]);
        $this->credit($user, 1);
        $this->login($user);

        $free = $this->startRace()->assertCreated();
        $this->abandonRace($free->json('item.id'))->assertCreated();
        $this->startRace()->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'grammar_race.entry_charged',
        ]);
        Queue::assertNotPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof DeliverStoredNotificationPush,
        );
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
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'grammar_race.level_up',
            'title' => 'Новый уровень в игре',
            'body' => 'Теперь ваш уровень в игре «Гонка местоимений» повышен до «Гонщик местоимений 2 уровня».',
            'deduplication_key' => 'grammar-race:level-up:personal_pronouns:2',
        ]);
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

    public function test_early_mistake_allows_a_second_answer_after_the_bot_reacts(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $start = $this->startRace(playMode: 'training')->assertCreated();
        $session = $start->json('item');
        $firstTask = $session['tasks'][0];
        $wrongAnswer = collect($firstTask['options'])
            ->firstWhere('id', '!=', $firstTask['correctAnswer'])['id'];

        GrammarRaceTask::query()
            ->where('grammar_race_session_id', $session['id'])
            ->where('position', $firstTask['position'])
            ->update(['bot_answer' => $wrongAnswer]);

        $payload = $this->winningPayload($session, (string) Str::uuid());
        $payload['rounds'][0] = [
            'taskPosition' => $firstTask['position'],
            'playerAnswer' => $wrongAnswer,
            'playerAnswerMs' => 1000,
            'secondPlayerAnswer' => $firstTask['correctAnswer'],
            'secondPlayerAnswerMs' => 1499,
        ];
        $url = '/api/v1/grammar-race-sessions/'.$session['id'].'/complete';

        $this->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rounds.0.secondPlayerAnswerMs');

        $payload['rounds'][0]['secondPlayerAnswerMs'] = $firstTask['botDelayMs']
            + $session['difficulty']['answerGraceMs']
            + 1;
        $this->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rounds.0.secondPlayerAnswerMs');

        $payload['rounds'][0]['secondPlayerAnswerMs'] = 1500;
        $this->postJson($url, $payload)
            ->assertCreated()
            ->assertJsonPath('item.status', 'student_won')
            ->assertJsonPath('item.rounds.0.secondPlayerAnswer', $firstTask['correctAnswer'])
            ->assertJsonPath('item.rounds.0.secondPlayerAnswerMs', 1500);

        $this->assertDatabaseHas('grammar_race_rounds', [
            'grammar_race_session_id' => $session['id'],
            'sequence' => 1,
            'player_answer' => $wrongAnswer,
            'player_answer_ms' => 1000,
            'second_player_answer' => $firstTask['correctAnswer'],
            'second_player_answer_ms' => 1500,
            'outcome' => 'student',
        ]);
    }

    private function login(User $user): void
    {
        if (! $user->info()->exists()) {
            $user->info()->create(['first_grade_year' => now()->year - 4]);
            $user->unsetRelation('info');
        }

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

    private function startRace(
        ?string $requestId = null,
        string $playMode = 'competitive',
        string $gameCode = 'personal_pronouns',
    ) {
        return $this->postJson("/api/v1/grammar-race-games/{$gameCode}/sessions", [
            'clientRequestId' => $requestId ?? (string) Str::uuid(),
            'playMode' => $playMode,
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
