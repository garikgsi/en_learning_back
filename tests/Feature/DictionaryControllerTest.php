<?php

namespace Tests\Feature;

use App\Enums\LangCode;
use App\Models\Exercise;
use App\Models\ExerciseItemResult;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\UserWordRepetition;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DictionaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dictionary_filters_words_by_users_available_grade(): void
    {
        $user = $this->userWithFirstGradeYear(now()->year - 3);

        $availableWord = $this->createWord('дом', 'home', 3);
        $this->createWord('университет', 'university', 4);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary')
            ->assertOk()
            ->assertJsonPath('availableGrade', 3)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', $availableWord->id);
    }

    public function test_dictionary_searches_both_languages_case_insensitively(): void
    {
        $user = $this->userWithFirstGradeYear(now()->year - 5);

        $home = $this->createWord('дом', 'Home', 1);
        $school = $this->createWord('Школа', 'School', 1);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary?search=дом')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', $home->id);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary?search=SCH')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', $school->id);
    }

    public function test_dictionary_paginates_results(): void
    {
        $user = $this->userWithFirstGradeYear(now()->year - 5);

        $this->createWord('альфа', 'alpha', 1);
        $second = $this->createWord('бета', 'beta', 1);
        $this->createWord('гамма', 'gamma', 1);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary?page=2&perPage=1')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('page', 2)
            ->assertJsonPath('perPage', 1)
            ->assertJsonPath('lastPage', 3)
            ->assertJsonPath('items.0.id', $second->id);
    }

    public function test_dictionary_counts_repeats_only_for_current_user(): void
    {
        $this->seed(LangSeeder::class);

        $user = $this->userWithFirstGradeYear(now()->year - 5);
        $otherUser = $this->userWithFirstGradeYear(now()->year - 5);
        $word = $this->createWord('дом', 'home', 1);
        $exercise = $this->createExercise($user);
        $otherExercise = $this->createExercise($otherUser);

        $this->createResult($exercise, $word, 0);
        $this->createResult($exercise, $word, 2);
        $this->createResult($otherExercise, $word, 0);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary')
            ->assertOk()
            ->assertJsonPath('items.0.repeatCount', 2)
            ->assertJsonPath('items.0.successfulRepeatCount', 1)
            ->assertJsonPath('items.0.failedRepeatCount', 1);
    }

    public function test_dictionary_returns_active_repetition_flag_for_current_user(): void
    {
        $user = $this->userWithFirstGradeYear(now()->year - 5);
        $otherUser = $this->userWithFirstGradeYear(now()->year - 5);
        $activeWord = $this->createWord('активное', 'active', 1);
        $inactiveWord = $this->createWord('неактивное', 'inactive', 1);

        UserWordRepetition::query()->create([
            'user_id' => $user->id,
            'word_id' => $activeWord->id,
            'is_active' => true,
        ]);
        UserWordRepetition::query()->create([
            'user_id' => $user->id,
            'word_id' => $inactiveWord->id,
            'is_active' => false,
        ]);
        UserWordRepetition::query()->create([
            'user_id' => $otherUser->id,
            'word_id' => $inactiveWord->id,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary')
            ->assertOk();

        $items = collect($response->json('items'))->keyBy('id');

        $this->assertTrue($items[$activeWord->id]['is_active']);
        $this->assertFalse($items[$inactiveWord->id]['is_active']);
    }

    public function test_search_treats_like_wildcards_as_plain_text(): void
    {
        $user = $this->userWithFirstGradeYear(now()->year - 5);

        $percentWord = $this->createWord('сто процентов', '100%', 1);
        $this->createWord('обычное слово', 'ordinary word', 1);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary?search=%25')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', $percentWord->id);
    }

    public function test_dictionary_requires_user_info(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary')
            ->assertConflict()
            ->assertJsonPath('code', 'USER_INFO_REQUIRED');
    }

    public function test_dictionary_requires_authentication(): void
    {
        $this->getJson('/api/v1/dictionary')
            ->assertUnauthorized();
    }

    private function userWithFirstGradeYear(int $year): User
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => $year,
        ]);

        return $user;
    }

    private function createWord(string $ru, string $en, int $grade): Word
    {
        return Word::query()->create([
            'ru' => $ru,
            'en' => $en,
            'grade' => $grade,
        ]);
    }

    private function createExercise(User $user): Exercise
    {
        $type = ExerciseType::query()->firstOrCreate(
            ['name' => 'translation'],
            ['title' => 'Translate the word'],
        );

        return Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->id,
            'dueDate' => now()->addDay(),
        ]);
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }

    private function createResult(
        Exercise $exercise,
        Word $word,
        int $errorsCount,
    ): ExerciseItemResult {
        $item = $exercise->items()->firstOrCreate([
            'word_id' => $word->id,
        ]);
        $complete = $exercise->completions()->create();

        return $complete->itemResults()->create([
            'exercise_item_id' => $item->id,
            'errors_count' => $errorsCount,
            'hints_count' => 0,
            'lang_id' => LangCode::en->value,
            'variants' => ['home'],
        ]);
    }
}
