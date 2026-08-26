<?php

namespace Tests\Feature;

use App\Models\Word;
use Database\Seeders\WordsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_clean_primary_words_and_explicit_variants(): void
    {
        $this->seed(WordsSeeder::class);

        $maths = Word::query()
            ->where('ru', 'математика')
            ->where('en', 'maths')
            ->sole();
        $watch = Word::query()
            ->where('ru', 'смотреть')
            ->where('en', 'watch')
            ->sole();

        $this->assertSame(['mathematics', 'math'], $maths->en_variants);
        $this->assertSame(['наблюдать', 'часы (наручные)'], $watch->ru_variants);
        $this->assertDatabaseHas('words', [
            'ru' => 'большой',
            'en' => 'big',
            'en_variants' => json_encode(['large']),
        ]);
        $this->assertDatabaseHas('words', [
            'ru' => 'приятный',
            'en' => 'pleasant',
            'en_variants' => json_encode(['nice', 'enjoyable']),
        ]);
        $this->assertDatabaseMissing('words', ['ru' => 'умный', 'en' => 'cute']);
        $this->assertDatabaseMissing('words', ['ru' => 'приятный', 'en' => 'enjoy']);
        $this->assertDatabaseHas('words', [
            'ru' => 'Идёт дождь',
            'en' => 'It\'s raining',
        ]);
        $this->assertDatabaseHas('words', [
            'ru' => 'Спасибо, хорошо',
            'en' => 'I\'m fine, thanks',
            'ru_variants' => '[]',
            'en_variants' => '[]',
        ]);
        $this->assertDatabaseMissing('words', ['ru' => 'дом (строение)']);
        $this->assertDatabaseMissing('words', ['en' => 'Mathematics (Math)']);

        Word::query()->each(function (Word $word): void {
            foreach ([$word->ru, $word->en, ...$word->ru_variants, ...$word->en_variants] as $value) {
                $this->assertMatchesRegularExpression("~^[\\p{L}() ,'!?-]+$~u", $value);

                if (str_contains($value, ',')) {
                    $this->assertContains($value, [
                        "I'm fine, thanks",
                        'PSHE (Personal, Social and Health Education)',
                        'Спасибо, хорошо',
                        'Вот, пожалуйста',
                    ]);
                }
            }
        });
    }
}
