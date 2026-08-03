<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Exceptions\NoWordsAvailableException;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\UserWordRepetition;
use App\Models\Word;
use App\Services\ExerciseService;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_create_an_exercise_without_words(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - 1,
        ]);
        $this->seed(ExerciseTypesSeeder::class);

        $this->expectException(NoWordsAvailableException::class);
        $this->expectExceptionMessage('Нет слов в словаре');

        try {
            app(ExerciseService::class)->create(
                ExerciseTypeCode::daily,
                $user,
                now(),
            );
        } finally {
            $this->assertDatabaseCount('exercise', 0);
        }
    }

    public function test_it_creates_exercise_with_fifteen_random_available_words_by_default(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - 3,
        ]);
        $type = ExerciseType::query()->create([
            'name' => 'translation',
            'title' => 'Translate the word',
        ]);

        foreach (range(1, 20) as $number) {
            Word::query()->create([
                'ru' => "слово{$number}",
                'en' => "word{$number}",
                'grade' => 3,
            ]);
        }

        Word::query()->create([
            'ru' => 'недоступное слово',
            'en' => 'unavailable word',
            'grade' => 4,
        ]);

        $dueDate = now()->addDay();
        $exercise = app(ExerciseService::class)->create($type, $user, $dueDate);

        $this->assertCount(15, $exercise->items);
        $this->assertTrue($exercise->type->is($type));
        $this->assertTrue($exercise->user->is($user));
        $this->assertTrue(
            $exercise->items->every(
                fn ($item): bool => $item->word->grade <= $user->grade,
            ),
        );
        $this->assertSame(3, $user->grade);
    }

    public function test_it_uses_requested_words_count(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - 1,
        ]);
        $this->seed(ExerciseTypesSeeder::class);

        foreach (range(1, 5) as $number) {
            Word::query()->create([
                'ru' => "слово{$number}",
                'en' => "word{$number}",
                'grade' => 1,
            ]);
        }

        $exercise = app(ExerciseService::class)->create(
            ExerciseTypeCode::daily,
            $user,
            now()->addDay(),
            3,
        );

        $this->assertCount(3, $exercise->items);
        $this->assertSame(ExerciseTypeCode::daily->value, $exercise->type->id);
        $this->assertSame(ExerciseTypeCode::daily->name, $exercise->type->name);
    }

    public function test_it_excludes_phrases_in_either_language(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - 1,
        ]);
        $this->seed(ExerciseTypesSeeder::class);

        $word = Word::query()->create([
            'ru' => 'дом',
            'en' => 'home',
            'grade' => 1,
        ]);
        Word::query()->create([
            'ru' => 'мой дом',
            'en' => 'home',
            'grade' => 1,
        ]);
        Word::query()->create([
            'ru' => 'школа',
            'en' => 'my school',
            'grade' => 1,
        ]);

        $exercise = app(ExerciseService::class)->create(
            ExerciseTypeCode::daily,
            $user,
            now()->addDay(),
        );

        $this->assertSame(
            [$word->id],
            $exercise->items->pluck('word_id')->all(),
        );
    }

    public function test_it_prioritizes_any_user_selected_word_without_deactivating_it(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - 1,
        ]);
        $this->seed(ExerciseTypesSeeder::class);

        Word::query()->create([
            'ru' => 'обычное',
            'en' => 'regular',
            'grade' => 1,
        ]);
        $selectedPhrase = Word::query()->create([
            'ru' => 'сложная выбранная фраза',
            'en' => 'hard selected phrase',
            'grade' => 99,
        ]);
        $repetition = UserWordRepetition::query()->create([
            'user_id' => $user->id,
            'word_id' => $selectedPhrase->id,
            'is_active' => true,
        ]);

        $exercise = app(ExerciseService::class)->create(
            ExerciseTypeCode::daily,
            $user,
            now()->addDay(),
            1,
        );

        $this->assertSame(
            [$selectedPhrase->id],
            $exercise->items->pluck('word_id')->all(),
        );
        $this->assertTrue($repetition->refresh()->is_active);
    }
}
