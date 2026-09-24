<?php

use App\Enums\ExerciseTypeCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercise_type')->updateOrInsert(
            ['id' => ExerciseTypeCode::userPlural->value],
            [
                'name' => ExerciseTypeCode::userPlural->name,
                'title' => ExerciseTypeCode::userPlural->title(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! DB::table('exercise')->where('type_id', ExerciseTypeCode::userPlural->value)->exists()) {
            DB::table('exercise_type')
                ->where('id', ExerciseTypeCode::userPlural->value)
                ->delete();
        }
    }
};
