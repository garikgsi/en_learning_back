<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Word;
use App\Services\ExerciseService;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseServiceTest extends TestCase
{
    use RefreshDatabase;

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
                'ru' => "слово {$number}",
                'en' => "word {$number}",
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
                'ru' => "слово {$number}",
                'en' => "word {$number}",
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
}
