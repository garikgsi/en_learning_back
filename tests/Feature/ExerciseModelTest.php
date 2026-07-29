<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseItem;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Word;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_exercise_relations_are_available_from_both_sides(): void
    {
        $user = User::factory()->create();
        $type = ExerciseType::query()->create([
            'name' => 'translation',
            'title' => 'Translate the word',
        ]);
        $word = Word::query()->create([
            'ru' => 'слово',
            'en' => 'word',
            'grade' => 1,
        ]);

        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->id,
            'dueDate' => now()->addDay(),
        ]);
        $item = $exercise->items()->create([
            'word_id' => $word->id,
        ]);

        $this->assertTrue($exercise->type->is($type));
        $this->assertTrue($type->exercises->first()->is($exercise));
        $this->assertTrue($exercise->user->is($user));
        $this->assertTrue($user->exercises->first()->is($exercise));
        $this->assertTrue($exercise->items->first()->is($item));
        $this->assertTrue($item->exercise->is($exercise));
        $this->assertTrue($item->word->is($word));
        $this->assertTrue($word->exerciseItems->first()->is($item));
        $this->assertInstanceOf(\DateTimeInterface::class, $exercise->dueDate);
    }

    public function test_referenced_exercise_records_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $type = ExerciseType::query()->create([
            'name' => 'translation',
            'title' => 'Translate the word',
        ]);
        $word = Word::query()->create([
            'ru' => 'слово',
            'en' => 'word',
            'grade' => 1,
        ]);
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->id,
            'dueDate' => now()->addDay(),
        ]);
        ExerciseItem::query()->create([
            'exercise_id' => $exercise->id,
            'word_id' => $word->id,
        ]);

        $this->expectException(QueryException::class);

        $exercise->delete();
    }
}
