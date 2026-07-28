<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;

class WordsSeeder extends Seeder
{
    public function run(): void
    {
        $words = [
            ['ru' => 'один', 'en' => 'one', 'grade' => 1],
            ['ru' => 'два', 'en' => 'two', 'grade' => 1],
            ['ru' => 'три', 'en' => 'three', 'grade' => 1],
            ['ru' => 'четыре', 'en' => 'four', 'grade' => 1],
            ['ru' => 'пять', 'en' => 'five', 'grade' => 1],
            ['ru' => 'шесть', 'en' => 'six', 'grade' => 1],
            ['ru' => 'семь', 'en' => 'seven', 'grade' => 1],
            ['ru' => 'восемь', 'en' => 'eight', 'grade' => 1],
            ['ru' => 'девять', 'en' => 'nine', 'grade' => 1],
            ['ru' => 'десять', 'en' => 'ten', 'grade' => 1],
            ['ru' => 'одиннадцать', 'en' => 'eleven', 'grade' => 1],
            ['ru' => 'двенадцать', 'en' => 'twelve', 'grade' => 1],
            ['ru' => 'тринадцать', 'en' => 'thirteen', 'grade' => 1],
            ['ru' => 'четырнадцать', 'en' => 'fourteen', 'grade' => 1],
            ['ru' => 'пятнадцать', 'en' => 'fifteen', 'grade' => 1],
            ['ru' => 'шестнадцать', 'en' => 'sixteen', 'grade' => 1],
            ['ru' => 'семнадцать', 'en' => 'seventeen', 'grade' => 1],
            ['ru' => 'восемнадцать', 'en' => 'eighteen', 'grade' => 1],
            ['ru' => 'девятнадцать', 'en' => 'nineteen', 'grade' => 1],
            ['ru' => 'птица', 'en' => 'bird', 'grade' => 2],
            ['ru' => 'кошка', 'en' => 'cat', 'grade' => 2],
            ['ru' => 'школа', 'en' => 'school', 'grade' => 2],
            ['ru' => 'дом', 'en' => 'home', 'grade' => 2],
            ['ru' => 'сегодня', 'en' => 'today', 'grade' => 2],
            ['ru' => 'завтра', 'en' => 'tomorrow', 'grade' => 2],
            ['ru' => 'книга', 'en' => 'book', 'grade' => 2],
            ['ru' => 'город', 'en' => 'city', 'grade' => 2],
            ['ru' => 'семья', 'en' => 'family', 'grade' => 2],
            ['ru' => 'друг', 'en' => 'friend', 'grade' => 2],
            ['ru' => 'сад', 'en' => 'garden', 'grade' => 2],
            ['ru' => 'дом', 'en' => 'house', 'grade' => 2],
            ['ru' => 'язык', 'en' => 'language', 'grade' => 2],
            ['ru' => 'утро', 'en' => 'morning', 'grade' => 2],
            ['ru' => 'ночь', 'en' => 'night', 'grade' => 2],
            ['ru' => 'вопрос', 'en' => 'question', 'grade' => 2],
            ['ru' => 'река', 'en' => 'river', 'grade' => 2],
            ['ru' => 'улица', 'en' => 'street', 'grade' => 2],
            ['ru' => 'учитель', 'en' => 'teacher', 'grade' => 2],
            ['ru' => 'окно', 'en' => 'window', 'grade' => 2],
            ['ru' => 'ответ', 'en' => 'answer', 'grade' => 2],
            ['ru' => 'машина', 'en' => 'car', 'grade' => 2],
            ['ru' => 'дверь', 'en' => 'door', 'grade' => 2],
            ['ru' => 'вечер', 'en' => 'evening', 'grade' => 2],
            ['ru' => 'цветок', 'en' => 'flower', 'grade' => 2],
            ['ru' => 'игра', 'en' => 'game', 'grade' => 2],
            ['ru' => 'урок', 'en' => 'lesson', 'grade' => 2],
            ['ru' => 'музыка', 'en' => 'music', 'grade' => 2],
            ['ru' => 'стол', 'en' => 'table', 'grade' => 2],
            ['ru' => 'вода', 'en' => 'water', 'grade' => 2],
            ['ru' => 'полное имя', 'en' => 'full name', 'grade' => 5],
            ['ru' => 'домашний адрес', 'en' => 'home address', 'grade' => 5],
            ['ru' => 'удостоверение личности', 'en' => 'identity card', 'grade' => 5],
            ['ru' => 'идентификационный номер', 'en' => 'identification number', 'grade' => 5],
            ['ru' => 'вступать в клуб', 'en' => 'join a club', 'grade' => 5],
            ['ru' => 'членский билет (карта)', 'en' => 'membership card', 'grade' => 5],
            ['ru' => 'телефонный номер', 'en' => 'telephone number', 'grade' => 5],
            ['ru' => 'записываться в библиотеку', 'en' => 'register at the library', 'grade' => 5],
            ['ru' => 'возраст', 'en' => 'age', 'grade' => 5],
            ['ru' => 'тетя', 'en' => 'aunt', 'grade' => 5],
            ['ru' => 'большой', 'en' => 'big', 'grade' => 5],
            ['ru' => 'брат', 'en' => 'brother', 'grade' => 5],
            ['ru' => 'ребенок', 'en' => 'child', 'grade' => 5],
            ['ru' => 'дети', 'en' => 'children', 'grade' => 5],
            ['ru' => 'двоюродный брат/двоюродная сестра', 'en' => 'cousin', 'grade' => 5],
            ['ru' => 'кудрявый', 'en' => 'curly', 'grade' => 5],
            ['ru' => 'дочь', 'en' => 'daughter', 'grade' => 5],
            ['ru' => 'папа', 'en' => 'dad', 'grade' => 5],
            ['ru' => 'светлый', 'en' => 'fair', 'grade' => 5],
            ['ru' => 'толстый', 'en' => 'fat', 'grade' => 5],
            ['ru' => 'седой', 'en' => 'grey', 'grade' => 5],
            ['ru' => 'волосы', 'en' => 'hair', 'grade' => 5],
            ['ru' => 'рост', 'en' => 'height', 'grade' => 5],
            ['ru' => 'муж', 'en' => 'husband', 'grade' => 5],
            ['ru' => 'длинный', 'en' => 'long', 'grade' => 5],
            ['ru' => 'среднего возраста', 'en' => 'middle age', 'grade' => 5],
            ['ru' => 'мама', 'en' => 'mum', 'grade' => 5],
            ['ru' => 'старый', 'en' => 'old', 'grade' => 5],
            ['ru' => 'родители', 'en' => 'parents', 'grade' => 5],
            ['ru' => 'короткий', 'en' => 'short', 'grade' => 5],
            ['ru' => 'сестра', 'en' => 'sister', 'grade' => 5],
            ['ru' => 'стройный', 'en' => 'slim', 'grade' => 5],
            ['ru' => 'сын', 'en' => 'son', 'grade' => 5],
            ['ru' => 'близнецы', 'en' => 'twins', 'grade' => 5],
            ['ru' => 'дядя', 'en' => 'uncle', 'grade' => 5],
            ['ru' => 'волнистые (о волосах)', 'en' => 'wavy', 'grade' => 5],
            ['ru' => 'вес', 'en' => 'weight', 'grade' => 5],
            ['ru' => 'жена', 'en' => 'wife', 'grade' => 5],
            ['ru' => 'молодой', 'en' => 'young', 'grade' => 5],
            ['ru' => 'быть немногим старше шестидесяти', 'en' => "be in one's early sixties", 'grade' => 5],
            ['ru' => 'быть немногим младше сорока', 'en' => 'be in late thirties', 'grade' => 5],
            ['ru' => 'быть в возрасте 25 лет', 'en' => 'be in mid twenties', 'grade' => 5],
            ['ru' => 'быть женатым, замужем', 'en' => 'be married to smb', 'grade' => 5],
            ['ru' => 'черты лица', 'en' => 'facial features', 'grade' => 5],
            ...require database_path('seeders/data/words_grade_4.php'),
            ...require database_path('seeders/data/words_grade_5_extra.php'),
            ...require database_path('seeders/data/words_grade_6.php'),
        ];

        foreach ($words as $word) {
            $model = Word::query()->firstOrNew([
                'ru' => $word['ru'],
                'en' => $word['en'],
            ]);
            $model->grade = $model->exists
                ? min($model->grade, $word['grade'])
                : $word['grade'];
            $model->save();
        }
    }
}
