<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
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

        $this->createWord('один', 'one', 1);
        $second = $this->createWord('два', 'two', 1);
        $this->createWord('три', 'three', 1);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dictionary?page=2&perPage=1')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('page', 2)
            ->assertJsonPath('perPage', 1)
            ->assertJsonPath('lastPage', 3)
            ->assertJsonPath('items.0.id', $second->id);
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

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
