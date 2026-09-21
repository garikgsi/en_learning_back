<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\Plural;
use App\Models\User;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use App\Services\Dictionary\Data\SpeechResult;
use App\Services\Dictionary\DictionarySpeechService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PluralExerciseTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_stores_singular_words_and_plural_exceptions(): void
    {
        $this->assertDatabaseCount('plurals', 33);
        $man = Word::query()
            ->where('ru', 'мужчина')
            ->where('en', 'man')
            ->sole();

        $this->assertSame(4, $man->grade);
        $this->assertSame('/mæn/', $man->transcription);
        $this->assertDatabaseHas('plurals', [
            'word_id' => $man->id,
            'plural_en' => 'men',
            'plural_ru' => 'мужчины',
            'plural_transcription' => '/men/',
        ]);
        $this->assertDatabaseMissing('words', [
            'ru' => 'мужчины',
            'en' => 'men',
        ]);
    }

    public function test_thursday_creates_plural_exercises_with_age_based_pair_counts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-30 12:00:00'));
        $this->seed(ExerciseTypesSeeder::class);
        $younger = $this->userInGrade(5);
        $older = $this->userInGrade(6);

        $this->artisan('exercises:create-daily')->assertSuccessful();
        $this->artisan('exercises:create-daily')->assertSuccessful();

        $exercises = Exercise::query()
            ->where('type_id', ExerciseTypeCode::plural->value)
            ->with('items.word.plural')
            ->get()
            ->keyBy('user_id');

        $this->assertCount(2, $exercises);
        $this->assertCount(10, $exercises[$younger->id]->items);
        $this->assertCount(15, $exercises[$older->id]->items);
        $this->assertTrue($exercises->every(
            fn (Exercise $exercise): bool => $exercise->items->every(
                fn ($item): bool => $item->word->plural instanceof Plural,
            ),
        ));
    }

    public function test_api_returns_plural_form_with_the_singular_word(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();
        $man = Word::query()->where('en', 'man')->where('ru', 'мужчина')->sole();
        $exercise = $user->exercises()->create([
            'type_id' => ExerciseTypeCode::plural->value,
            'dueDate' => today(),
        ]);
        $exercise->items()->create(['word_id' => $man->id]);

        $this->withToken(app(AuthTokenService::class)->issue($user)['accessToken'])
            ->getJson("/api/v1/exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('item.type.name', 'plural')
            ->assertJsonPath('item.items.0.word.en', 'man')
            ->assertJsonPath('item.items.0.plural.en', 'men')
            ->assertJsonPath('item.items.0.plural.ru', 'мужчины')
            ->assertJsonPath('item.items.0.plural.transcription', '/men/');
    }

    public function test_plural_audio_uses_the_plural_form(): void
    {
        $plural = Plural::query()->where('plural_en', 'men')->sole();
        $speech = Mockery::mock(DictionarySpeechService::class);
        $speech->shouldReceive('audio')
            ->once()
            ->with(Mockery::on(
                fn ($request): bool => $request->text === 'men',
            ))
            ->andReturn(new SpeechResult('plural mp3', 'audio/mpeg'));
        $this->app->instance(DictionarySpeechService::class, $speech);

        $this->get("/api/v1/dictionary/plurals/{$plural->id}/audio")
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertContent('plural mp3');
    }

    private function userInGrade(int $grade): User
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - $grade,
        ]);

        return $user;
    }
}
