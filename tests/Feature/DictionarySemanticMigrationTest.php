<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DictionarySemanticMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_dictionary_words_without_losing_relations(): void
    {
        $now = now();
        DB::table('words')->insert([
            [
                'id' => 60,
                'ru' => 'большой',
                'en' => 'big',
                'ru_variants' => '[]',
                'en_variants' => json_encode(['great']),
                'grade' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 539,
                'ru' => 'большой',
                'en' => 'great',
                'ru_variants' => json_encode(['огромный', 'замечательный', 'великий']),
                'en_variants' => json_encode(['big']),
                'grade' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $user = User::factory()->create();
        $typeId = DB::table('exercise_type')->insertGetId([
            'name' => 'test',
            'title' => 'Тест',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $exerciseId = DB::table('exercise')->insertGetId([
            'user_id' => $user->id,
            'type_id' => $typeId,
            'dueDate' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $itemId = DB::table('exercise_items')->insertGetId([
            'exercise_id' => $exerciseId,
            'word_id' => 539,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_word_repetition')->insert([
            [
                'user_id' => $user->id,
                'word_id' => 60,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $user->id,
                'word_id' => 539,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $migration = require database_path(
            'migrations/2026_08_26_000021_split_ambiguous_dictionary_words.php',
        );
        $migration->up();

        $this->assertDatabaseMissing('words', ['id' => 539]);
        $this->assertDatabaseHas('words', [
            'id' => 60,
            'ru' => 'большой',
            'en' => 'big',
            'ru_variants' => '[]',
            'en_variants' => json_encode(['large']),
        ]);
        $this->assertDatabaseHas('words', [
            'ru' => 'огромный',
            'en' => 'huge',
            'en_variants' => json_encode(['enormous']),
        ]);
        $this->assertDatabaseHas('exercise_items', [
            'id' => $itemId,
            'word_id' => 60,
        ]);
        $this->assertDatabaseHas('user_word_repetition', [
            'user_id' => $user->id,
            'word_id' => 60,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('user_word_repetition', 1);
    }
}
