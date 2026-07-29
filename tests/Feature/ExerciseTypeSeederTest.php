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

    public function test_it_seeds_daily_and_weekly_types_idempotently(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $this->seed(ExerciseTypesSeeder::class);

        $this->assertDatabaseCount('exercise_type', 2);
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
    }
}
