<?php

use App\Enums\ExerciseTypeCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plurals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('word_id')->unique()->constrained('words');
            $table->string('plural_en');
            $table->string('plural_ru');
            $table->string('plural_transcription')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('exercise_type')->updateOrInsert(
            ['id' => ExerciseTypeCode::plural->value],
            [
                'name' => ExerciseTypeCode::plural->name,
                'title' => ExerciseTypeCode::plural->title(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach (require database_path('seeders/data/plural_exceptions.php') as $exception) {
            $wordId = DB::table('words')
                ->where('ru', $exception['ru'])
                ->where('en', $exception['en'])
                ->orderBy('id')
                ->value('id');

            if ($wordId === null) {
                $wordId = DB::table('words')->insertGetId([
                    'ru' => $exception['ru'],
                    'en' => $exception['en'],
                    'ru_variants' => '[]',
                    'en_variants' => '[]',
                    'transcription' => $exception['transcription'],
                    'grade' => 4,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('words')->where('id', $wordId)->update([
                    'grade' => 4,
                    'transcription' => $exception['transcription'],
                    'updated_at' => $now,
                ]);
            }

            DB::table('plurals')->updateOrInsert(
                ['word_id' => $wordId],
                [
                    'plural_en' => $exception['plural_en'],
                    'plural_ru' => $exception['plural_ru'],
                    'plural_transcription' => $exception['plural_transcription'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plurals');

        if (! DB::table('exercise')->where('type_id', ExerciseTypeCode::plural->value)->exists()) {
            DB::table('exercise_type')
                ->where('id', ExerciseTypeCode::plural->value)
                ->delete();
        }
    }
};
