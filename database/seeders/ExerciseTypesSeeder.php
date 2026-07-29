<?php

namespace Database\Seeders;

use App\Enums\ExerciseTypeCode;
use App\Models\ExerciseType;
use Illuminate\Database\Seeder;

class ExerciseTypesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ExerciseTypeCode::cases() as $code) {
            $type = ExerciseType::query()->findOrNew($code->value);

            $type->forceFill([
                'id' => $code->value,
                'name' => $code->name,
                'title' => $code->title(),
            ])->save();
        }
    }
}
