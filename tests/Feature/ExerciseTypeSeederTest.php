<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\ExerciseType;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_all_exercise_types_idempotently(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $this->seed(ExerciseTypesSeeder::class);

        $this->assertDatabaseCount('exercise_type', 3);
        $this->assertDatabaseHas('exercise_type', [
            'id' => ExerciseTypeCode::daily->value,
            'name' => ExerciseTypeCode::daily->name,
            'title' => ExerciseTypeCode::daily->title(),
        ]);
        $this->assertDatabaseHas('exercise_type', [
            'id' => ExerciseTypeCode::weekly->value,
            'name' => ExerciseTypeCode::weekly->name,
            'title' => ExerciseTypeCode::weekly->title(),
        ]);
        $this->assertDatabaseHas('exercise_type', [
            'id' => ExerciseTypeCode::user->value,
            'name' => ExerciseTypeCode::user->name,
            'title' => ExerciseTypeCode::user->title(),
        ]);
    }

    public function test_model_sugar_resolves_seeded_types(): void
    {
        $this->seed(ExerciseTypesSeeder::class);

        $this->assertSame(
            ExerciseTypeCode::daily->value,
            ExerciseType::forCode(ExerciseTypeCode::daily)->id,
        );
        $this->assertSame(
            ExerciseTypeCode::weekly->value,
            ExerciseType::forCode(ExerciseTypeCode::weekly)->id,
        );
        $this->assertSame(
            ExerciseTypeCode::user->value,
            ExerciseType::forCode(ExerciseTypeCode::user)->id,
        );
    }
}
