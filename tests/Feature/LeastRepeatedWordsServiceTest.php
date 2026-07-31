<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Enums\LangCode;
use App\Models\Exercise;
use App\Models\ExerciseItemResult;
use App\Models\User;
use App\Models\Word;
use App\Services\LeastRepeatedWordsService;
use Database\Seeders\ExerciseTypesSeeder;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeastRepeatedWordsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_users_least_repeated_available_words(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = $this->createUserWithGrade(3);
        $otherUser = $this->createUserWithGrade(3);
        $neverRepeated = $this->createWord('never', 3);
        $repeatedOnce = $this->createWord('once', 3);
        $repeatedTwice = $this->createWord('twice', 3);
        $this->createWord('too-hard', 4);
        Word::query()->create([
            'ru' => 'два слова',
            'en' => 'phrase',
            'grade' => 3,
        ]);

        $this->addResults($user, $repeatedOnce, 1);
        $this->addResults($user, $repeatedTwice, 2);
        $this->addResults($otherUser, $neverRepeated, 2);
        $this->addResults(
            $user,
            $neverRepeated,
            3,
            ExerciseTypeCode::user,
        );

        $words = app(LeastRepeatedWordsService::class)->get($user->id, 2);

        $this->assertSame(
            [$repeatedOnce->id, $repeatedTwice->id],
            array_map(fn (Word $word): int => $word->id, $words),
        );
    }

    private function createUserWithGrade(int $grade): User
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - $grade,
        ]);

        return $user;
    }

    private function createWord(string $en, int $grade): Word
    {
        return Word::query()->create([
            'ru' => "слово-{$en}",
            'en' => $en,
            'grade' => $grade,
        ]);
    }

    private function addResults(
        User $user,
        Word $word,
        int $count,
        ExerciseTypeCode $type = ExerciseTypeCode::daily,
    ): void {
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->value,
            'dueDate' => now(),
        ]);
        $item = $exercise->items()->create(['word_id' => $word->id]);

        foreach (range(1, $count) as $index) {
            $complete = $exercise->completions()->create();
            ExerciseItemResult::query()->create([
                'exercise_complete_id' => $complete->id,
                'exercise_item_id' => $item->id,
                'errors_count' => 0,
                'hints_count' => 0,
                'lang_id' => LangCode::en->value,
                'variants' => ["variant-{$index}"],
            ]);
        }
    }
}
