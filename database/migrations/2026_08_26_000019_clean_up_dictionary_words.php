<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateWord(55, 'членский билет', 'membership card', ['членская карта']);
        $this->updateWord(289, 'спасибо', 'thank you');
        $this->updateWord(349, 'синий', 'blue', ['голубой']);
        $this->updateWord(350, 'пирог', 'cake', ['торт']);
        $this->updateWord(352, 'кот', 'cat', ['кошка']);
        $this->updateWord(363, 'заканчивать', 'finish', ['финиш', 'окончание']);
        $this->updateWord(480, 'американец', 'American', ['американский']);
        $this->updateWord(481, 'британец', 'British', ['британский']);
        $this->updateWord(482, 'канадец', 'Canadian', ['канадский']);
        $this->updateWord(484, 'англичанин', 'English', ['английский']);
        $this->updateWord(485, 'француз', 'French', ['французский']);
        $this->updateWord(486, 'итальянец', 'Italian', ['итальянский']);
        $this->updateWord(487, 'японец', 'Japanese', ['японский']);
        $this->updateWord(504, 'смотреть', 'watch', ['наблюдать', 'часы (наручные)']);
        $this->updateWord(886, 'Идёт дождь', 'It\'s raining');
        $this->updateWord(1301, 'встречать', 'meet', ['встречаться']);
        $this->updateWord(1303, 'ставить палатку', 'put up a tent');

        DB::table('words')->whereIn('id', [250, 435])->delete();
    }

    public function down(): void
    {
        $this->updateWord(55, 'членский билет (карта)', 'membership card');
        $this->updateWord(289, 'Спасибо!', 'Thank you!');
        $this->updateWord(349, 'синий, голубой', 'blue');
        $this->updateWord(350, 'пирог, торт', 'cake');
        $this->updateWord(352, 'кот, кошка', 'cat');
        $this->updateWord(363, 'финиш, окончание / заканчивать', 'finish');
        $this->updateWord(480, 'американец / американский', 'American');
        $this->updateWord(481, 'британец / британский', 'British');
        $this->updateWord(482, 'канадец / канадский', 'Canadian');
        $this->updateWord(484, 'англичанин / английский', 'English');
        $this->updateWord(485, 'француз / французский', 'French');
        $this->updateWord(486, 'итальянец / итальянский', 'Italian');
        $this->updateWord(487, 'японец / японский', 'Japanese');
        $this->updateWord(504, 'смотреть, наблюдать / часы', 'watch');
        $this->updateWord(886, 'Идёт (сильный) дождь.', 'It\'s raining (heavily).');
        $this->updateWord(1301, 'встречать(ся)', 'meet');
        $this->updateWord(1303, 'ставить (палатку)', 'put up (a tent)');

        DB::table('words')->insertOrIgnore([
            [
                'id' => 250,
                'ru' => 'дом (строение)',
                'en' => 'house',
                'ru_variants' => '[]',
                'en_variants' => json_encode(['home'], JSON_THROW_ON_ERROR),
                'grade' => 4,
            ],
            [
                'id' => 435,
                'ru' => 'математика',
                'en' => 'Mathematics (Math)',
                'ru_variants' => '[]',
                'en_variants' => json_encode(['maths'], JSON_THROW_ON_ERROR),
                'grade' => 5,
            ],
        ]);
    }

    /** @param list<string> $ruVariants */
    private function updateWord(
        int $id,
        string $ru,
        string $en,
        array $ruVariants = [],
    ): void {
        DB::table('words')
            ->where('id', $id)
            ->update([
                'ru' => $ru,
                'en' => $en,
                'ru_variants' => json_encode(
                    $ruVariants,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
                'en_variants' => '[]',
            ]);
    }
};
