<?php

namespace App\Services\GrammarRace;

use App\Enums\GrammarRaceArticle;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\Data\GeneratedGrammarRaceTask;
use App\Services\GrammarRace\Data\PersonalPronounLexicon;
use InvalidArgumentException;

class ArticleTaskGenerator implements GrammarRaceTaskGenerator
{
    /** @var list<string> */
    private const A_NOUNS = [
        'bag', 'ball', 'banana', 'bike', 'bird', 'book', 'box', 'boy', 'bus', 'cake',
        'car', 'cat', 'chair', 'computer', 'cup', 'desk', 'dog', 'door', 'flower', 'friend',
        'game', 'girl', 'hat', 'house', 'jacket', 'kite', 'lamp', 'map', 'pen', 'pencil',
        'phone', 'rabbit', 'room', 'school', 'table', 'teacher', 'toy', 'tree', 'watch', 'window',
    ];

    /** @var list<string> */
    private const AN_NOUNS = [
        'apple', 'animal', 'ant', 'answer', 'arm', 'egg', 'elephant', 'email', 'engine', 'eraser',
        'idea', 'insect', 'island', 'orange', 'owl', 'umbrella', 'uncle', 'aunt', 'artist', 'actor',
        'office', 'oven', 'eye', 'ear', 'airport', 'address', 'exercise', 'ice cream', 'octopus', 'onion',
    ];

    /**
     * @param  array<string, mixed>  $level
     * @return list<GeneratedGrammarRaceTask>
     */
    public function generate(array $level, int $count, float $reactionMultiplier = 1): array
    {
        if (! isset($level['content_level'], $level['bot_error_percent'], $level['bot_min_delay_ms'], $level['bot_max_delay_ms'])) {
            throw new InvalidArgumentException('Incomplete article level configuration.');
        }

        $contentLevel = (int) $level['content_level'];
        $options = $this->options();
        $tasks = [];

        foreach ($this->balancedCandidates($this->candidates($contentLevel), $count) as $candidate) {
            $correct = GrammarRaceArticle::from($candidate['answer']);
            $botAnswer = $this->botAnswer($correct, $options, (int) $level['bot_error_percent']);
            $tasks[] = new GeneratedGrammarRaceTask(
                type: 'single_choice',
                payload: [
                    'text' => $candidate['prompt'],
                    'translation' => ($level['show_translation'] ?? false)
                        ? $this->feedbackTranslation($candidate)
                        : null,
                    'instruction' => 'Выберите правильный артикль',
                    'feedback' => [
                        'correctText' => $candidate['correctText'],
                        'translation' => $this->feedbackTranslation($candidate),
                        'explanation' => $candidate['explanation'],
                    ],
                ],
                options: array_map(
                    fn (GrammarRaceArticle $article): array => [
                        'id' => $article->value,
                        'label' => $article->label(),
                    ],
                    $options,
                ),
                correctAnswer: $correct->value,
                botAnswer: $botAnswer->value,
                botDelayMs: random_int(
                    (int) round($level['bot_min_delay_ms'] * $reactionMultiplier),
                    (int) round($level['bot_max_delay_ms'] * $reactionMultiplier),
                ),
            );
        }

        return $tasks;
    }

    /** @return list<GrammarRaceArticle> */
    private function options(): array
    {
        return GrammarRaceArticle::cases();
    }

    /**
     * @return list<array{prompt: string, answer: string, correctText: string, explanation: string}>
     */
    private function candidates(int $level): array
    {
        return match ($level) {
            1 => $this->basicPhrases(),
            2 => $this->describedPhrases(),
            3 => [...$this->describedPhrases(), ...$this->possessivePhrases()],
            4 => [...$this->basicPhrases(), ...$this->possessivePhrases(), ...$this->definitePhrases()],
            5 => [
                ...$this->describedPhrases(),
                ...$this->possessivePhrases(),
                ...$this->definitePhrases(),
                ...$this->properNamePhrases(),
            ],
            6 => $this->indefiniteSentences(),
            7 => [...$this->indefiniteSentences(), ...$this->secondMentionSentences()],
            8 => [
                ...$this->indefiniteSentences(),
                ...$this->secondMentionSentences(),
                ...$this->generalMeaningSentences(),
                ...$this->uniqueThingSentences(),
            ],
            9 => [
                ...$this->indefiniteSentences(),
                ...$this->secondMentionSentences(),
                ...$this->generalMeaningSentences(),
                ...$this->possessiveSentences(),
                ...$this->uniqueThingSentences(),
            ],
            10 => [
                ...$this->indefiniteSentences(),
                ...$this->soundExceptionSentences(),
                ...$this->secondMentionSentences(),
                ...$this->generalMeaningSentences(),
                ...$this->possessiveSentences(),
                ...$this->uniqueThingSentences(),
            ],
            default => throw new InvalidArgumentException("Unsupported article content level: {$level}."),
        };
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function basicPhrases(): array
    {
        $result = [];
        foreach (self::A_NOUNS as $noun) {
            $result[] = $this->candidate(
                "___ {$noun}", 'a', "a {$noun}",
                'Перед согласным звуком ставим a.',
            );
        }
        foreach (self::AN_NOUNS as $noun) {
            $result[] = $this->candidate(
                "___ {$noun}", 'an', "an {$noun}",
                'Перед гласным звуком ставим an.',
            );
        }

        return $result;
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function describedPhrases(): array
    {
        $aAdjectives = ['big', 'blue', 'clean', 'funny', 'good', 'green', 'happy', 'kind', 'new', 'nice', 'red', 'small', 'tall', 'white', 'young'];
        $anAdjectives = ['angry', 'empty', 'English', 'interesting', 'old', 'orange', 'open', 'easy'];
        $result = [];

        foreach ($aAdjectives as $index => $adjective) {
            foreach (array_slice(self::A_NOUNS, ($index * 2) % 30, 4) as $noun) {
                $phrase = "{$adjective} {$noun}";
                $result[] = $this->candidate(
                    "___ {$phrase}", 'a', "a {$phrase}",
                    "Первым слышится согласный звук в слове «{$adjective}», поэтому ставим a.",
                );
            }
        }
        foreach ($anAdjectives as $index => $adjective) {
            foreach (array_slice(self::A_NOUNS, ($index * 3) % 28, 4) as $noun) {
                $phrase = "{$adjective} {$noun}";
                $result[] = $this->candidate(
                    "___ {$phrase}", 'an', "an {$phrase}",
                    "Первым слышится гласный звук в слове «{$adjective}», поэтому ставим an.",
                );
            }
        }

        return $result;
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function possessivePhrases(): array
    {
        $result = [];
        foreach (['my', 'your', 'his', 'her', 'its', 'our', 'their'] as $owner) {
            foreach (array_slice(self::A_NOUNS, 0, 8) as $noun) {
                $phrase = "{$owner} {$noun}";
                $result[] = $this->candidate(
                    "___ {$phrase}", 'none', $phrase,
                    "Перед {$owner} артикль не ставится: это слово уже показывает, кому принадлежит предмет.",
                );
            }
        }

        return $result;
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function definitePhrases(): array
    {
        $phrases = [
            ['sun', 'the Sun'], ['moon', 'the Moon'], ['sky', 'the sky'], ['world', 'the world'],
            ['Earth', 'the Earth'], ['North Pole', 'the North Pole'], ['South Pole', 'the South Pole'],
            ['equator', 'the equator'], ['Milky Way', 'the Milky Way'], ['solar system', 'the solar system'],
            ['universe', 'the universe'], ['ground', 'the ground'], ['internet', 'the internet'],
        ];

        return array_map(fn (array $phrase): array => $this->candidate(
            "___ {$phrase[0]}", 'the', $phrase[1],
            'Мы говорим о конкретном или единственном объекте, поэтому ставим the.',
        ), $phrases);
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function properNamePhrases(): array
    {
        $names = ['Tom', 'Anna', 'Kate', 'London', 'Moscow', 'Paris', 'Rome', 'France', 'Spain', 'Europe', 'Africa', 'Monday', 'July', 'English'];

        return array_map(fn (string $name): array => $this->candidate(
            "___ {$name}", 'none', $name,
            'Перед этим именем или названием артикль не ставится.',
        ), $names);
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function indefiniteSentences(): array
    {
        $frames = [
            ['I can see ___ %s.', 'I can see %s %s.'],
            ['She has got ___ %s.', 'She has got %s %s.'],
            ['He wants ___ %s.', 'He wants %s %s.'],
            ['There is ___ %s in the room.', 'There is %s %s in the room.'],
            ['We need ___ %s.', 'We need %s %s.'],
        ];
        $result = [];

        foreach (self::A_NOUNS as $index => $noun) {
            [$prompt, $correct] = $frames[$index % count($frames)];
            $result[] = $this->candidate(
                sprintf($prompt, $noun), 'a', sprintf($correct, 'a', $noun),
                'Мы впервые говорим об одном предмете. Перед согласным звуком ставим a.',
            );
        }
        foreach (self::AN_NOUNS as $index => $noun) {
            [$prompt, $correct] = $frames[$index % count($frames)];
            $result[] = $this->candidate(
                sprintf($prompt, $noun), 'an', sprintf($correct, 'an', $noun),
                'Мы впервые говорим об одном предмете. Перед гласным звуком ставим an.',
            );
        }

        return $result;
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function secondMentionSentences(): array
    {
        $adjectives = ['friendly', 'small', 'new', 'red', 'funny', 'clean', 'beautiful', 'old', 'open', 'near me'];
        $result = [];
        foreach (array_slice(self::A_NOUNS, 0, 24) as $index => $noun) {
            $ending = $adjectives[$index % count($adjectives)];
            $result[] = $this->candidate(
                "I can see a {$noun}. ___ {$noun} is {$ending}.",
                'the',
                "I can see a {$noun}. The {$noun} is {$ending}.",
                'Мы уже назвали этот предмет, поэтому теперь ставим the.',
            );
        }

        return $result;
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function generalMeaningSentences(): array
    {
        $pairs = [
            ['___ cats like milk.', 'Cats like milk.'],
            ['___ dogs are good friends.', 'Dogs are good friends.'],
            ['___ birds can fly.', 'Birds can fly.'],
            ['___ books help us learn.', 'Books help us learn.'],
            ['___ trees need water.', 'Trees need water.'],
            ['___ apples are good for you.', 'Apples are good for you.'],
            ['___ elephants are big animals.', 'Elephants are big animals.'],
            ['___ teachers help children.', 'Teachers help children.'],
            ['___ water is important.', 'Water is important.'],
            ['___ milk is white.', 'Milk is white.'],
            ['___ snow is cold.', 'Snow is cold.'],
            ['___ music makes me happy.', 'Music makes me happy.'],
            ['___ English is my favourite subject.', 'English is my favourite subject.'],
            ['___ football is popular.', 'Football is popular.'],
            ['___ breakfast is ready.', 'Breakfast is ready.'],
            ['___ summer is warm here.', 'Summer is warm here.'],
            ['___ children like games.', 'Children like games.'],
            ['___ fish live in water.', 'Fish live in water.'],
        ];

        return array_map(fn (array $pair): array => $this->candidate(
            $pair[0], 'none', $pair[1],
            'Мы говорим о предметах или веществе вообще, поэтому артикль не нужен.',
        ), $pairs);
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function uniqueThingSentences(): array
    {
        $pairs = [
            ['___ sun gives us light.', 'The Sun gives us light.'],
            ['___ moon is bright tonight.', 'The Moon is bright tonight.'],
            ['Look at ___ sky.', 'Look at the sky.'],
            ['___ Earth goes around the Sun.', 'The Earth goes around the Sun.'],
            ['We live in ___ world around us.', 'We live in the world around us.'],
            ['___ North Pole is very cold.', 'The North Pole is very cold.'],
            ['___ South Pole is in Antarctica.', 'The South Pole is in Antarctica.'],
            ['___ equator goes around the Earth.', 'The equator goes around the Earth.'],
            ['___ Milky Way is our galaxy.', 'The Milky Way is our galaxy.'],
            ['Earth is in ___ solar system.', 'Earth is in the solar system.'],
            ['___ universe is very large.', 'The universe is very large.'],
            ['The ball is on ___ ground.', 'The ball is on the ground.'],
            ['We found the answer on ___ internet.', 'We found the answer on the internet.'],
            ['___ Thames flows through London.', 'The Thames flows through London.'],
            ['___ Atlantic Ocean is very large.', 'The Atlantic Ocean is very large.'],
        ];

        return array_map(fn (array $pair): array => $this->candidate(
            $pair[0], 'the', $pair[1],
            'Мы говорим о конкретном или единственном объекте, поэтому ставим the.',
        ), $pairs);
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function possessiveSentences(): array
    {
        $pairs = [
            ['This is ___ my book.', 'This is my book.', 'my'],
            ['That is ___ your bag.', 'That is your bag.', 'your'],
            ['I can see ___ his bike.', 'I can see his bike.', 'his'],
            ['She is with ___ her friend.', 'She is with her friend.', 'her'],
            ['The dog is in ___ its house.', 'The dog is in its house.', 'its'],
            ['We are in ___ our classroom.', 'We are in our classroom.', 'our'],
            ['They are playing with ___ their ball.', 'They are playing with their ball.', 'their'],
            ['Where is ___ my pencil?', 'Where is my pencil?', 'my'],
            ['He opened ___ his book.', 'He opened his book.', 'his'],
            ['She put on ___ her jacket.', 'She put on her jacket.', 'her'],
            ['We like ___ our teacher.', 'We like our teacher.', 'our'],
            ['They cleaned ___ their room.', 'They cleaned their room.', 'their'],
            ['Is this ___ your phone?', 'Is this your phone?', 'your'],
            ['The cat is eating ___ its food.', 'The cat is eating its food.', 'its'],
            ['I am doing ___ my homework.', 'I am doing my homework.', 'my'],
        ];

        return array_map(fn (array $pair): array => $this->candidate(
            $pair[0], 'none', $pair[1],
            "Перед {$pair[2]} артикль не ставится: это слово уже показывает, кому принадлежит предмет.",
        ), $pairs);
    }

    /** @return list<array{prompt: string, answer: string, correctText: string, explanation: string}> */
    private function soundExceptionSentences(): array
    {
        $pairs = [
            ['He studies at ___ university.', 'a', 'He studies at a university.', 'В слове university первым слышится звук «й», поэтому ставим a.'],
            ['She wears ___ uniform at school.', 'a', 'She wears a uniform at school.', 'В слове uniform первым слышится звук «й», поэтому ставим a.'],
            ['This is ___ useful book.', 'a', 'This is a useful book.', 'В слове useful первым слышится звук «й», поэтому ставим a.'],
            ['She waited for ___ hour.', 'an', 'She waited for an hour.', 'В слове hour буква h не произносится. Первым слышится гласный звук, поэтому ставим an.'],
            ['He is ___ honest boy.', 'an', 'He is an honest boy.', 'В слове honest буква h не произносится. Первым слышится гласный звук, поэтому ставим an.'],
        ];

        return array_map(fn (array $pair): array => $this->candidate(
            $pair[0], $pair[1], $pair[2], $pair[3],
        ), $pairs);
    }

    /**
     * @param  list<array{prompt: string, answer: string, correctText: string, explanation: string}>  $candidates
     * @return list<array{prompt: string, answer: string, correctText: string, explanation: string}>
     */
    private function balancedCandidates(array $candidates, int $count): array
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $groups[$candidate['answer']][] = $candidate;
        }
        foreach ($groups as &$group) {
            shuffle($group);
        }
        unset($group);

        $answers = array_keys($groups);
        $indices = array_fill_keys($answers, 0);
        $result = [];
        $previous = null;

        while (count($result) < $count) {
            shuffle($answers);
            if (count($answers) > 1 && $answers[0] === $previous) {
                [$answers[0], $answers[1]] = [$answers[1], $answers[0]];
            }
            foreach ($answers as $answer) {
                if (count($result) >= $count) {
                    break;
                }
                $group = $groups[$answer];
                $result[] = $group[$indices[$answer] % count($group)];
                $indices[$answer]++;
                $previous = $answer;
            }
        }

        return $result;
    }

    /** @param list<GrammarRaceArticle> $options */
    private function botAnswer(GrammarRaceArticle $correct, array $options, int $errorPercent): GrammarRaceArticle
    {
        if (random_int(1, 10000) > $errorPercent * 100) {
            return $correct;
        }

        return $this->pick(array_values(array_filter(
            $options,
            fn (GrammarRaceArticle $article): bool => $article !== $correct,
        )));
    }

    /** @param array{prompt: string, answer: string, correctText: string, explanation: string} $candidate */
    private function feedbackTranslation(array $candidate): string
    {
        $correctText = $candidate['correctText'];
        $lowerText = mb_strtolower($correctText);

        $fixed = [
            'the sun' => 'Солнце', 'the moon' => 'Луна', 'the sky' => 'небо',
            'the world' => 'мир', 'the earth' => 'Земля', 'the north pole' => 'Северный полюс',
            'the south pole' => 'Южный полюс', 'the equator' => 'экватор',
            'the milky way' => 'Млечный Путь', 'the solar system' => 'Солнечная система',
            'the universe' => 'Вселенная', 'the ground' => 'земля', 'the internet' => 'интернет',
            'the thames' => 'Темза', 'the atlantic ocean' => 'Атлантический океан',
            'london' => 'Лондон', 'moscow' => 'Москва', 'paris' => 'Париж', 'rome' => 'Рим',
            'france' => 'Франция', 'spain' => 'Испания', 'europe' => 'Европа', 'africa' => 'Африка',
            'monday' => 'понедельник', 'july' => 'июль', 'english' => 'английский язык',
            'tom' => 'Том', 'anna' => 'Анна', 'kate' => 'Кейт',
        ];
        foreach ($fixed as $english => $russian) {
            if (str_contains($lowerText, $english)) {
                return $russian;
            }
        }

        $objects = PersonalPronounLexicon::objects();
        $extra = [
            ['en' => 'actor', 'plural' => 'actors', 'ru' => 'актёр', 'ruPlural' => 'актёры', 'gender' => 'm'],
            ['en' => 'address', 'plural' => 'addresses', 'ru' => 'адрес', 'ruPlural' => 'адреса', 'gender' => 'm'],
            ['en' => 'airport', 'plural' => 'airports', 'ru' => 'аэропорт', 'ruPlural' => 'аэропорты', 'gender' => 'm'],
            ['en' => 'boy', 'plural' => 'boys', 'ru' => 'мальчик', 'ruPlural' => 'мальчики', 'gender' => 'm'],
            ['en' => 'girl', 'plural' => 'girls', 'ru' => 'девочка', 'ruPlural' => 'девочки', 'gender' => 'f'],
            ['en' => 'friend', 'plural' => 'friends', 'ru' => 'друг', 'ruPlural' => 'друзья', 'gender' => 'm'],
            ['en' => 'school', 'plural' => 'schools', 'ru' => 'школа', 'ruPlural' => 'школы', 'gender' => 'f'],
            ['en' => 'teacher', 'plural' => 'teachers', 'ru' => 'учитель', 'ruPlural' => 'учителя', 'gender' => 'm'],
            ['en' => 'house', 'plural' => 'houses', 'ru' => 'дом', 'ruPlural' => 'дома', 'gender' => 'm'],
            ['en' => 'lamp', 'plural' => 'lamps', 'ru' => 'лампа', 'ruPlural' => 'лампы', 'gender' => 'f'],
            ['en' => 'map', 'plural' => 'maps', 'ru' => 'карта', 'ruPlural' => 'карты', 'gender' => 'f'],
            ['en' => 'table', 'plural' => 'tables', 'ru' => 'стол', 'ruPlural' => 'столы', 'gender' => 'm'],
            ['en' => 'animal', 'plural' => 'animals', 'ru' => 'животное', 'ruPlural' => 'животные', 'gender' => 'n'],
            ['en' => 'answer', 'plural' => 'answers', 'ru' => 'ответ', 'ruPlural' => 'ответы', 'gender' => 'm'],
            ['en' => 'ant', 'plural' => 'ants', 'ru' => 'муравей', 'ruPlural' => 'муравьи', 'gender' => 'm'],
            ['en' => 'arm', 'plural' => 'arms', 'ru' => 'рука', 'ruPlural' => 'руки', 'gender' => 'f'],
            ['en' => 'artist', 'plural' => 'artists', 'ru' => 'художник', 'ruPlural' => 'художники', 'gender' => 'm'],
            ['en' => 'aunt', 'plural' => 'aunts', 'ru' => 'тётя', 'ruPlural' => 'тёти', 'gender' => 'f'],
            ['en' => 'bird', 'plural' => 'birds', 'ru' => 'птица', 'ruPlural' => 'птицы', 'gender' => 'f'],
            ['en' => 'bus', 'plural' => 'buses', 'ru' => 'автобус', 'ruPlural' => 'автобусы', 'gender' => 'm'],
            ['en' => 'ear', 'plural' => 'ears', 'ru' => 'ухо', 'ruPlural' => 'уши', 'gender' => 'n'],
            ['en' => 'elephant', 'plural' => 'elephants', 'ru' => 'слон', 'ruPlural' => 'слоны', 'gender' => 'm'],
            ['en' => 'email', 'plural' => 'emails', 'ru' => 'электронное письмо', 'ruPlural' => 'электронные письма', 'gender' => 'n'],
            ['en' => 'engine', 'plural' => 'engines', 'ru' => 'двигатель', 'ruPlural' => 'двигатели', 'gender' => 'm'],
            ['en' => 'idea', 'plural' => 'ideas', 'ru' => 'идея', 'ruPlural' => 'идеи', 'gender' => 'f'],
            ['en' => 'exercise', 'plural' => 'exercises', 'ru' => 'упражнение', 'ruPlural' => 'упражнения', 'gender' => 'n'],
            ['en' => 'eye', 'plural' => 'eyes', 'ru' => 'глаз', 'ruPlural' => 'глаза', 'gender' => 'm'],
            ['en' => 'ice cream', 'plural' => 'ice creams', 'ru' => 'мороженое', 'ruPlural' => 'мороженое', 'gender' => 'n'],
            ['en' => 'insect', 'plural' => 'insects', 'ru' => 'насекомое', 'ruPlural' => 'насекомые', 'gender' => 'n'],
            ['en' => 'octopus', 'plural' => 'octopuses', 'ru' => 'осьминог', 'ruPlural' => 'осьминоги', 'gender' => 'm'],
            ['en' => 'office', 'plural' => 'offices', 'ru' => 'офис', 'ruPlural' => 'офисы', 'gender' => 'm'],
            ['en' => 'oven', 'plural' => 'ovens', 'ru' => 'духовка', 'ruPlural' => 'духовки', 'gender' => 'f'],
            ['en' => 'owl', 'plural' => 'owls', 'ru' => 'сова', 'ruPlural' => 'совы', 'gender' => 'f'],
            ['en' => 'toy', 'plural' => 'toys', 'ru' => 'игрушка', 'ruPlural' => 'игрушки', 'gender' => 'f'],
            ['en' => 'umbrella', 'plural' => 'umbrellas', 'ru' => 'зонт', 'ruPlural' => 'зонты', 'gender' => 'm'],
            ['en' => 'uncle', 'plural' => 'uncles', 'ru' => 'дядя', 'ruPlural' => 'дяди', 'gender' => 'm'],
            ['en' => 'watch', 'plural' => 'watches', 'ru' => 'наручные часы', 'ruPlural' => 'наручные часы', 'gender' => 'm'],
            ['en' => 'hour', 'plural' => 'hours', 'ru' => 'час', 'ruPlural' => 'часы', 'gender' => 'm'],
            ['en' => 'university', 'plural' => 'universities', 'ru' => 'университет', 'ruPlural' => 'университеты', 'gender' => 'm'],
            ['en' => 'uniform', 'plural' => 'uniforms', 'ru' => 'форма', 'ruPlural' => 'формы', 'gender' => 'f'],
        ];

        foreach ([...$objects, ...$extra] as $object) {
            if (preg_match('/\b'.preg_quote($object['plural'], '/').'\b/iu', $lowerText) === 1) {
                return $this->upperFirst($object['ruPlural']);
            }
            if (preg_match('/\b'.preg_quote($object['en'], '/').'\b/iu', $lowerText) === 1) {
                return $this->upperFirst($object['ru']);
            }
        }

        return match ($candidate['answer']) {
            'a', 'an' => 'Один предмет',
            'the' => 'Конкретный предмет',
            default => 'Без артикля',
        };
    }

    private function upperFirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }

    /** @return array{prompt: string, answer: string, correctText: string, explanation: string} */
    private function candidate(string $prompt, string $answer, string $correctText, string $explanation): array
    {
        return compact('prompt', 'answer', 'correctText', 'explanation');
    }

    /** @template T @param list<T> $items @return T */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
