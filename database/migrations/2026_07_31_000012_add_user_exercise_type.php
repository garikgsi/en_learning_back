<?php

use App\Enums\ExerciseTypeCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercise_type')->updateOrInsert(
            ['id' => ExerciseTypeCode::user->value],
            [
                'name' => ExerciseTypeCode::user->name,
                'title' => ExerciseTypeCode::user->title(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('exercise_type')
            ->where('id', ExerciseTypeCode::user->value)
            ->delete();
    }
};
