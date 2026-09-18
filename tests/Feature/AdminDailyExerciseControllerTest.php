<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Enums\UserRole;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Carbon\CarbonInterface;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDailyExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseTypesSeeder::class);
        $this->travelTo(now()->startOfDay()->addHours(12));
    }

    public function test_endpoints_require_an_authenticated_admin(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $payload = $this->payload($user, [$word->id]);
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $this->postJson('/api/v1/admin/exercises/daily', $payload)->assertUnauthorized();
        $this->withToken($this->token($user));
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson('/api/v1/admin/exercises/daily', $payload)->assertForbidden();
        $this->assertDatabaseCount('exercise', 0);
    }

    public function test_user_list_contains_recipient_details_without_secrets(): void
    {
        $user = User::factory()->create(['name' => 'Анна']);
        $user->info()->create(['first_grade_year' => now()->year - 5]);
        $this->loginAdmin();
        $response = $this->getJson('/api/v1/admin/users')->assertOk();
        $item = collect($response->json('items'))->firstWhere('id', $user->id);
        $this->assertSame(['id', 'name', 'phone', 'grade'], array_keys($item));
        $this->assertSame(5, $item['grade']);
        $this->assertSame($user->phone, $item['phone']);
    }

    public function test_manual_assignment_preserves_all_selected_phrases_and_any_grade_in_order(): void
    {
        $user = User::factory()->create(); // Manual assignment needs no grade information.
        $phrase = $this->word('доброе утро', 'good morning', 99);
        $sameTranslation = $this->word('утро', 'good morning', 1);
        $word = $this->word();
        $this->loginAdmin();
        $response = $this->postJson('/api/v1/admin/exercises/daily', $this->payload($user, [$phrase->id, $sameTranslation->id, $word->id]))
            ->assertCreated()->assertJsonPath('wasReplaced', false)
            ->assertJsonPath('item.userId', $user->id)
            ->assertJsonPath('item.type.name', 'daily')->assertJsonCount(3, 'item.items')
            ->assertJsonPath('item.items.0.word.en', 'good morning');
        $exercise = Exercise::query()->findOrFail($response->json('item.id'));
        $this->assertTrue($exercise->dueDate->equalTo(today()));
        $this->assertSame([$phrase->id, $sameTranslation->id, $word->id], $exercise->items->pluck('word_id')->all());
        $this->withToken($this->token($user))->getJson('/api/v1/exercises/current')
            ->assertOk()->assertJsonPath('items.0.id', $exercise->id);
    }

    public function test_checked_option_replaces_latest_today_pending_daily_only(): void
    {
        $user = User::factory()->create();
        $oldWord = $this->word();
        $newWord = $this->word('до свидания', 'good bye');
        $older = $this->exercise($user, $oldWord, today());
        $target = $this->exercise($user, $oldWord, now());
        $completed = $this->exercise($user, $oldWord, now());
        $completed->completions()->create();
        $untouched = [
            $older, $completed,
            $this->exercise($user, $oldWord, today()->subDay()),
            $this->exercise($user, $oldWord, today()->addDay()),
            $this->exercise($user, $oldWord, today(), ExerciseTypeCode::weekly),
            $this->exercise($user, $oldWord, today(), ExerciseTypeCode::user),
            $this->exercise(User::factory()->create(), $oldWord, today()),
        ];
        $oldItemId = $target->items->first()->id;
        $count = Exercise::query()->count();
        $this->loginAdmin();
        $this->postJson('/api/v1/admin/exercises/daily', $this->payload($user, [$newWord->id], true))
            ->assertOk()->assertJsonPath('wasReplaced', true)
            ->assertJsonPath('item.id', $target->id)->assertJsonPath('item.items.0.word.id', $newWord->id);
        $this->assertDatabaseCount('exercise', $count);
        $this->assertDatabaseMissing('exercise_items', ['id' => $oldItemId]);
        $this->assertTrue($target->refresh()->dueDate->equalTo(now()));
        foreach ($untouched as $exercise) {
            $this->assertSame([$oldWord->id], $exercise->refresh()->items->pluck('word_id')->all());
        }
        $this->assertDatabaseCount('exercise_complete', 1);
    }

    public function test_unchecked_option_creates_another_daily_even_if_one_is_pending(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $existing = $this->exercise($user, $word, today());
        $this->loginAdmin();
        $this->postJson('/api/v1/admin/exercises/daily', $this->payload($user, [$word->id]))
            ->assertCreated()->assertJsonPath('wasReplaced', false);
        $this->assertDatabaseCount('exercise', 2);
        $this->assertSame([$word->id], $existing->refresh()->items->pluck('word_id')->all());
    }

    public function test_checked_option_creates_new_when_only_completed_or_past_tasks_exist(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $completed = $this->exercise($user, $word, today());
        $completed->completions()->create();
        $this->exercise($user, $word, today()->subDay());
        $this->loginAdmin();
        $response = $this->postJson('/api/v1/admin/exercises/daily', $this->payload($user, [$word->id], true))
            ->assertCreated()->assertJsonPath('wasReplaced', false);
        $this->assertNotSame($completed->id, $response->json('item.id'));
        $this->assertDatabaseCount('exercise', 3);
        $this->assertSame([$word->id], $completed->refresh()->items->pluck('word_id')->all());
        $this->assertDatabaseCount('exercise_complete', 1);
    }

    public function test_invalid_selections_never_replace_existing_items(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $existing = $this->exercise($user, $word, today());
        $this->loginAdmin();
        foreach ([[], [$word->id, $word->id], [999999], ['phrase'], range(1, 101)] as $ids) {
            $this->postJson('/api/v1/admin/exercises/daily', $this->payload($user, $ids, true))->assertUnprocessable();
        }
        $this->postJson('/api/v1/admin/exercises/daily', [
            ...$this->payload($user, [$word->id], true), 'userId' => '01990b00-1234-7000-8000-123456789abc',
        ])->assertUnprocessable()->assertJsonValidationErrors('userId');
        $this->assertDatabaseCount('exercise', 1);
        $this->assertSame([$word->id], $existing->refresh()->items->pluck('word_id')->all());
    }

    public function test_admin_word_search_finds_phrases_in_both_languages_and_variants(): void
    {
        $word = $this->word('доброе утро', 'good morning', 99);
        $word->update(['ru_variants' => ['приветствие'], 'en_variants' => ['hello there']]);
        $this->loginAdmin();
        foreach (['доброе', 'GOOD MORNING', 'приветствие', 'hello there'] as $search) {
            $this->getJson('/api/v1/dictionary?'.http_build_query(['search' => $search]))
                ->assertOk()->assertJsonPath('items.0.id', $word->id);
        }
    }

    public function test_assignment_uses_selected_calendar_date_including_past_and_future(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $this->loginAdmin();
        foreach ([today()->subDays(2), today()->addDays(7)] as $date) {
            $response = $this->postJson('/api/v1/admin/exercises/daily', [
                ...$this->payload($user, [$word->id]), 'dueDate' => $date->toDateString(),
            ])->assertCreated();
            $exercise = Exercise::query()->findOrFail($response->json('item.id'));
            $this->assertTrue($exercise->dueDate->equalTo($date));
        }
        $this->withToken($this->token($user))->getJson('/api/v1/exercises/current')
            ->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_replacement_matches_the_selected_date_and_leaves_today_unchanged(): void
    {
        $user = User::factory()->create();
        $oldWord = $this->word();
        $newWord = $this->word('доброе утро', 'good morning');
        $date = today()->addDays(7);
        $target = $this->exercise($user, $oldWord, $date->copy()->endOfDay());
        $today = $this->exercise($user, $oldWord, today());
        $completed = $this->exercise($user, $oldWord, $date);
        $completed->completions()->create();
        $this->loginAdmin();
        $this->postJson('/api/v1/admin/exercises/daily', [
            ...$this->payload($user, [$newWord->id], true), 'dueDate' => $date->toDateString(),
        ])->assertOk()->assertJsonPath('wasReplaced', true)->assertJsonPath('item.id', $target->id);
        $this->assertSame([$newWord->id], $target->refresh()->items->pluck('word_id')->all());
        $this->assertSame([$oldWord->id], $today->refresh()->items->pluck('word_id')->all());
        $this->assertSame([$oldWord->id], $completed->refresh()->items->pluck('word_id')->all());
        $this->assertDatabaseCount('exercise', 3);
    }

    public function test_completed_exercise_on_selected_date_is_preserved_and_new_one_is_created(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $date = today()->addDays(7);
        $completed = $this->exercise($user, $word, $date);
        $completed->completions()->create();
        $this->loginAdmin();
        $response = $this->postJson('/api/v1/admin/exercises/daily', [
            ...$this->payload($user, [$word->id], true), 'dueDate' => $date->toDateString(),
        ])->assertCreated()->assertJsonPath('wasReplaced', false);
        $this->assertNotSame($completed->id, $response->json('item.id'));
        $this->assertDatabaseCount('exercise', 2);
        $this->assertDatabaseCount('exercise_complete', 1);
    }

    public function test_date_must_be_a_real_calendar_date_in_iso_format(): void
    {
        $user = User::factory()->create();
        $word = $this->word();
        $this->exercise($user, $word, today());
        $this->loginAdmin();
        foreach ([null, '', '20.09.2026', '2026-02-30', '2026-09-20T00:00:00Z'] as $date) {
            $this->postJson('/api/v1/admin/exercises/daily', [
                ...$this->payload($user, [$word->id], true), 'dueDate' => $date,
            ])->assertUnprocessable()->assertJsonValidationErrors('dueDate');
        }
        $this->assertDatabaseCount('exercise', 1);
        $this->assertDatabaseCount('exercise_items', 1);
    }

    private function loginAdmin(): void
    {
        $this->withToken($this->token(User::factory()->create(['role' => UserRole::admin])));
    }

    private function token(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }

    private function word(string $ru = 'дом', string $en = 'home', int $grade = 1): Word
    {
        return Word::query()->create(['ru' => $ru, 'en' => $en, 'grade' => $grade]);
    }

    private function exercise(User $user, Word $word, CarbonInterface $date, ExerciseTypeCode $type = ExerciseTypeCode::daily): Exercise
    {
        $exercise = $user->exercises()->create(['type_id' => $type->value, 'dueDate' => $date]);
        $exercise->items()->create(['word_id' => $word->id]);

        return $exercise;
    }

    private function payload(User $user, array $wordIds, bool $replaceExisting = false): array
    {
        return ['userId' => $user->id, 'wordIds' => $wordIds, 'dueDate' => today()->toDateString(), 'replaceExisting' => $replaceExisting];
    }
}
