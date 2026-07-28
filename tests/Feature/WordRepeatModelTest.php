<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Word;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordRepeatModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_has_many_user_repeats(): void
    {
        $user = User::factory()->create();
        $word = Word::query()->create([
            'ru' => 'слово',
            'en' => 'word',
            'grade' => 1,
        ]);

        $repeat = $word->repeats()->create([
            'user_id' => $user->id,
            'errors_count' => 2,
            'hints_count' => 1,
        ]);

        $this->assertTrue($word->refresh()->repeats->first()->is($repeat));
        $this->assertTrue($repeat->word->is($word));
        $this->assertTrue($repeat->user->is($user));
        $this->assertTrue($user->refresh()->wordRepeats->first()->is($repeat));
    }

    public function test_word_with_repeats_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $word = Word::query()->create([
            'ru' => 'слово',
            'en' => 'word',
            'grade' => 1,
        ]);
        $word->repeats()->create([
            'user_id' => $user->id,
            'errors_count' => 0,
            'hints_count' => 0,
        ]);

        $this->expectException(QueryException::class);

        $word->delete();
    }
}
