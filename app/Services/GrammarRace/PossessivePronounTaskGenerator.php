<?php

namespace App\Services\GrammarRace;

use App\Enums\PossessivePronoun;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\Data\GeneratedGrammarRaceTask;
use InvalidArgumentException;

class PossessivePronounTaskGenerator implements GrammarRaceTaskGenerator
{
    /** @var list<string> */
    private const OBJECTS = ['bag', 'bike', 'book', 'camera', 'computer', 'desk', 'jacket', 'phone', 'room', 'table', 'watch'];

    /** @var list<string> */
    private const ADJECTIVES = ['beautiful', 'black', 'blue', 'expensive', 'green', 'new', 'old', 'red', 'small', 'useful'];

    /** @var array<string, array{m: string, f: string, n: string, p: string}> */
    private const ADJECTIVE_TRANSLATIONS = [
        'beautiful' => ['m' => 'красивый', 'f' => 'красивая', 'n' => 'красивое', 'p' => 'красивые'],
        'black' => ['m' => 'чёрный', 'f' => 'чёрная', 'n' => 'чёрное', 'p' => 'чёрные'],
        'blue' => ['m' => 'синий', 'f' => 'синяя', 'n' => 'синее', 'p' => 'синие'],
        'expensive' => ['m' => 'дорогой', 'f' => 'дорогая', 'n' => 'дорогое', 'p' => 'дорогие'],
        'green' => ['m' => 'зелёный', 'f' => 'зелёная', 'n' => 'зелёное', 'p' => 'зелёные'],
        'new' => ['m' => 'новый', 'f' => 'новая', 'n' => 'новое', 'p' => 'новые'],
        'old' => ['m' => 'старый', 'f' => 'старая', 'n' => 'старое', 'p' => 'старые'],
        'red' => ['m' => 'красный', 'f' => 'красная', 'n' => 'красное', 'p' => 'красные'],
        'small' => ['m' => 'маленький', 'f' => 'маленькая', 'n' => 'маленькое', 'p' => 'маленькие'],
        'useful' => ['m' => 'полезный', 'f' => 'полезная', 'n' => 'полезное', 'p' => 'полезные'],
    ];

    /** @var list<string> */
    private const MALE_NAMES = ['Alex', 'Ben', 'Daniel', 'George', 'Harry', 'Jack', 'Max', 'Oliver', 'Peter', 'Tom'];

    /** @var list<string> */
    private const FEMALE_NAMES = ['Alice', 'Anna', 'Emma', 'Grace', 'Jane', 'Kate', 'Lucy', 'Mary', 'Olivia', 'Sophie'];

    /** @var list<string> */
    private const ANIMALS = ['cat', 'dog', 'hamster', 'horse', 'rabbit'];

    /** @var array<string, array{ru: string, gender: 'm'|'f'|'n'|'p'}> */
    private const OBJECT_TRANSLATIONS = [
        'bag' => ['ru' => 'сумка', 'gender' => 'f'],
        'bike' => ['ru' => 'велосипед', 'gender' => 'm'],
        'book' => ['ru' => 'книга', 'gender' => 'f'],
        'camera' => ['ru' => 'камера', 'gender' => 'f'],
        'computer' => ['ru' => 'компьютер', 'gender' => 'm'],
        'desk' => ['ru' => 'письменный стол', 'gender' => 'm'],
        'jacket' => ['ru' => 'куртка', 'gender' => 'f'],
        'phone' => ['ru' => 'телефон', 'gender' => 'm'],
        'room' => ['ru' => 'комната', 'gender' => 'f'],
        'table' => ['ru' => 'стол', 'gender' => 'm'],
        'watch' => ['ru' => 'часы', 'gender' => 'p'],
        'toy' => ['ru' => 'игрушка', 'gender' => 'f'],
        'bed' => ['ru' => 'лежанка', 'gender' => 'f'],
        'food' => ['ru' => 'еда', 'gender' => 'f'],
        'ball' => ['ru' => 'мяч', 'gender' => 'm'],
    ];

    /**
     * @param  array<string, mixed>  $level
     * @return list<GeneratedGrammarRaceTask>
     */
    public function generate(array $level, int $count, float $reactionMultiplier = 1): array
    {
        if (! isset($level['content_level'], $level['bot_error_percent'], $level['bot_min_delay_ms'], $level['bot_max_delay_ms'])) {
            throw new InvalidArgumentException('Incomplete possessive-pronoun level configuration.');
        }

        $tasks = [];
        $usedPrompts = [];

        foreach ($this->balancedPronouns($count) as $pronoun) {
            $prompt = '';

            for ($attempt = 0; $attempt < 30; $attempt++) {
                $prompt = $this->sentence($pronoun, (int) $level['content_level']);
                if (! isset($usedPrompts[$prompt])) {
                    break;
                }
            }

            $usedPrompts[$prompt] = true;
            $botAnswer = $this->botAnswer($pronoun, (int) $level['bot_error_percent']);
            $tasks[] = new GeneratedGrammarRaceTask(
                type: 'single_choice',
                payload: [
                    'text' => $prompt,
                    'translation' => null,
                    'feedback' => [
                        'correctText' => $this->completedText($prompt, $pronoun),
                        'translation' => $this->feedbackTranslation($prompt, $pronoun),
                        'explanation' => $this->explanation($pronoun),
                    ],
                ],
                options: $this->options(),
                correctAnswer: $pronoun->value,
                botAnswer: $botAnswer->value,
                botDelayMs: random_int(
                    (int) round($level['bot_min_delay_ms'] * $reactionMultiplier),
                    (int) round($level['bot_max_delay_ms'] * $reactionMultiplier),
                ),
            );
        }

        return $tasks;
    }

    /** @return list<PossessivePronoun> */
    private function balancedPronouns(int $count): array
    {
        $result = [];
        $previous = null;

        while (count($result) < $count) {
            $cycle = PossessivePronoun::cases();
            shuffle($cycle);

            if ($previous !== null && $cycle[0] === $previous) {
                [$cycle[0], $cycle[1]] = [$cycle[1], $cycle[0]];
            }

            foreach ($cycle as $pronoun) {
                if (count($result) === $count) {
                    break;
                }

                $result[] = $pronoun;
                $previous = $pronoun;
            }
        }

        return $result;
    }

    private function sentence(PossessivePronoun $pronoun, int $level): string
    {
        $template = random_int(1, min(5, max(1, $level)));
        $object = $this->pick(self::OBJECTS);
        $adjective = $this->pick(self::ADJECTIVES);

        return match ($pronoun) {
            PossessivePronoun::my => $this->mySentence($template, $object, $adjective),
            PossessivePronoun::your => $this->yourSentence($template, $object, $adjective),
            PossessivePronoun::his => $this->hisSentence($template, $object, $adjective),
            PossessivePronoun::her => $this->herSentence($template, $object, $adjective),
            PossessivePronoun::its => $this->itsSentence($template, $object, $adjective),
            PossessivePronoun::our => $this->ourSentence($template, $object, $adjective),
            PossessivePronoun::their => $this->theirSentence($template, $object, $adjective),
            PossessivePronoun::mine,
            PossessivePronoun::yours,
            PossessivePronoun::hers,
            PossessivePronoun::ours,
            PossessivePronoun::theirs => $this->independentSentence($pronoun, $template, $object),
        };
    }

    private function independentSentence(PossessivePronoun $pronoun, int $template, string $object): string
    {
        $owner = match ($pronoun) {
            PossessivePronoun::mine => 'I',
            PossessivePronoun::yours => 'You',
            PossessivePronoun::his => 'The boy',
            PossessivePronoun::hers => 'The girl',
            PossessivePronoun::ours => 'We',
            PossessivePronoun::theirs => 'The children',
            default => '',
        };
        $objectPronoun = match ($pronoun) {
            PossessivePronoun::mine => 'me',
            PossessivePronoun::yours => 'you',
            PossessivePronoun::his => 'him',
            PossessivePronoun::hers => 'her',
            PossessivePronoun::ours => 'us',
            PossessivePronoun::theirs => 'them',
            default => '',
        };
        $determiner = match ($pronoun) {
            PossessivePronoun::mine => 'my',
            PossessivePronoun::yours => 'your',
            PossessivePronoun::his => 'his',
            PossessivePronoun::hers => 'her',
            PossessivePronoun::ours => 'our',
            PossessivePronoun::theirs => 'their',
            default => '',
        };
        $haveVerb = in_array($pronoun, [PossessivePronoun::his, PossessivePronoun::hers], true)
            ? 'has'
            : 'have';

        return match ($template) {
            1 => "{$owner} {$haveVerb} got a {$object}. This {$object} is ___.",
            2 => "This {$object} belongs to {$objectPronoun}. It is ___.",
            3 => 'Here is '."{$determiner} {$object}. This {$object} is ___.",
            4 => ucfirst($determiner)." {$object} is here. This {$object} is ___.",
            default => "Is this {$determiner} {$object}? Yes, it is ___.",
        };
    }

    private function mySentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "I have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to me. It is ___ {$object}.",
            3 => "This is my {$object}. ___ {$object} is {$adjective}.",
            4 => "I left the {$object} at home. I need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to me. It is ___ {$object}.",
        };
    }

    private function yourSentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "You have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to you. It is ___ {$object}.",
            3 => "This is your {$object}. ___ {$object} is {$adjective}.",
            4 => "You left the {$object} at school. You need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to you. It is ___ {$object}.",
        };
    }

    private function hisSentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => $this->independentSentence(PossessivePronoun::his, $template, $object),
            2 => "This {$object} belongs to the boy. It is ___ {$object}.",
            3 => $this->independentSentence(PossessivePronoun::his, $template, $object),
            4 => "My brother left the {$object} at home. He needs ___ {$object} now.",
            default => $this->independentSentence(PossessivePronoun::his, $template, $object),
        };
    }

    private function herSentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "The girl has got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to the girl. It is ___ {$object}.",
            3 => "This is her {$object}. ___ {$object} is {$adjective}.",
            4 => "My sister left the {$object} at home. She needs ___ {$object} now.",
            default => "The girl has a {$object}, and this one belongs to her. It is ___ {$object}.",
        };
    }

    private function itsSentence(int $template, string $object, string $adjective): string
    {
        $animal = $this->pick(self::ANIMALS);

        return match ($template) {
            1 => "The {$animal} has got a toy. ___ toy is {$adjective}.",
            2 => "This bed belongs to the {$animal}. It is ___ bed.",
            3 => "The {$animal} is eating. ___ food is in the bowl.",
            4 => "The {$animal} is in the garden. ___ ball is near the tree.",
            default => "Every animal has a place to sleep. The {$animal} is in ___ bed.",
        };
    }

    private function ourSentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "We have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to us. It is ___ {$object}.",
            3 => "This is our {$object}. ___ {$object} is {$adjective}.",
            4 => "My brother and I left the {$object} at home. We need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to us. It is ___ {$object}.",
        };
    }

    private function theirSentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "The children have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to the children. It is ___ {$object}.",
            3 => "This is their {$object}. ___ {$object} is {$adjective}.",
            4 => "My parents left the {$object} at home. They need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to them. It is ___ {$object}.",
        };
    }

    private function botAnswer(PossessivePronoun $correct, int $errorPercent): PossessivePronoun
    {
        if (random_int(1, 10000) > $errorPercent * 100) {
            return $correct;
        }

        return $this->pick(array_values(array_filter(
            PossessivePronoun::cases(),
            fn (PossessivePronoun $pronoun): bool => $pronoun !== $correct,
        )));
    }

    private function completedText(string $prompt, PossessivePronoun $pronoun): string
    {
        $position = strpos($prompt, '___');
        if ($position === false) {
            return $prompt;
        }

        $beforeBlank = rtrim(substr($prompt, 0, $position));
        $answer = $pronoun->value;
        if ($beforeBlank === '' || preg_match('/[.!?]$/u', $beforeBlank) === 1) {
            $answer = ucfirst($answer);
        }

        return substr_replace($prompt, $answer, $position, 3);
    }

    private function explanation(PossessivePronoun $pronoun): string
    {
        return match ($pronoun) {
            PossessivePronoun::my => 'Предмет принадлежит мне (I), поэтому используем my.',
            PossessivePronoun::your => 'Предмет принадлежит тебе или вам (you), поэтому используем your.',
            PossessivePronoun::his => 'Когда мы знаем, что предмет принадлежит ему (he), используем his.',
            PossessivePronoun::her => 'Собственник — девочка или женщина (she), поэтому используем her.',
            PossessivePronoun::its => 'Собственник — животное или предмет (it), поэтому используем its.',
            PossessivePronoun::our => 'Предмет принадлежит нам (we), поэтому используем our.',
            PossessivePronoun::their => 'Предмет принадлежит нескольким людям, животным или предметам (they), поэтому используем their.',
            PossessivePronoun::mine => 'Когда мы знаем, что предмет принадлежит мне (I), можем сказать, что он мой (mine).',
            PossessivePronoun::yours => 'Когда мы знаем, что предмет принадлежит тебе или вам (you), можем сказать, что он твой или ваш (yours).',
            PossessivePronoun::hers => 'Когда мы знаем, что предмет принадлежит ей (she), можем сказать, что он её (hers).',
            PossessivePronoun::ours => 'Когда мы знаем, что предмет принадлежит нам (we), можем сказать, что он наш (ours).',
            PossessivePronoun::theirs => 'Когда мы знаем, что предмет принадлежит им (they), можем сказать, что он их (theirs).',
        };
    }

    private function feedbackTranslation(string $prompt, PossessivePronoun $pronoun): string
    {
        if (preg_match('/___\s+[a-z]/i', $prompt) !== 1) {
            return $this->independentFeedbackTranslation($prompt, $pronoun);
        }

        preg_match('/___\s+([a-z]+)/i', $prompt, $matches);
        $object = strtolower($matches[1] ?? '');
        $translation = self::OBJECT_TRANSLATIONS[$object] ?? ['ru' => $object, 'gender' => 'm'];
        $possessive = $this->russianPossessive($pronoun, $translation['gender']);
        $possessivePhrase = "{$possessive} {$translation['ru']}";
        $possessivePhrase = mb_strtoupper(mb_substr($possessivePhrase, 0, 1)).mb_substr($possessivePhrase, 1);
        $ownerHas = match ($pronoun) {
            PossessivePronoun::my => 'У меня',
            PossessivePronoun::your => 'У вас',
            PossessivePronoun::his => 'У мальчика',
            PossessivePronoun::her => 'У девочки',
            PossessivePronoun::our => 'У нас',
            PossessivePronoun::their => 'У детей',
            default => '',
        };
        $ownerDative = match ($pronoun) {
            PossessivePronoun::my => 'Мне',
            PossessivePronoun::your => 'Вам',
            PossessivePronoun::his => 'Ему',
            PossessivePronoun::her => 'Ей',
            PossessivePronoun::our => 'Нам',
            PossessivePronoun::their => 'Им',
            default => '',
        };

        if ($pronoun === PossessivePronoun::its) {
            return $this->itsFeedbackTranslation($prompt, $possessivePhrase);
        }

        preg_match('/ is ([a-z]+)\.$/i', $prompt, $adjectiveMatches);
        $adjective = self::ADJECTIVE_TRANSLATIONS[strtolower($adjectiveMatches[1] ?? '')][$translation['gender']] ?? '';

        if (str_contains($prompt, ' has got a ') || str_contains($prompt, ' have got a ')) {
            return "{$ownerHas} есть {$translation['ru']}. {$possessivePhrase} {$adjective}.";
        }

        if (str_starts_with($prompt, 'This is ')) {
            return "Это {$possessive} {$translation['ru']}. {$possessivePhrase} {$adjective}.";
        }

        if (str_starts_with($prompt, 'This ') && str_contains($prompt, ' belongs to ')) {
            return "{$ownerDative} принадлежит {$translation['ru']}. Это {$possessive} {$translation['ru']}.";
        }

        if (str_contains($prompt, ' left the ')) {
            $remained = $this->agreePossessive($translation['gender'], 'остался', 'осталась', 'осталось', 'остались');
            $needed = $this->agreePossessive($translation['gender'], 'нужен', 'нужна', 'нужно', 'нужны');
            $place = str_contains($prompt, ' at school') ? 'в школе' : 'дома';
            $ownerDativeLower = mb_strtolower($ownerDative);

            return "{$possessivePhrase} {$remained} {$place}. Сейчас {$ownerDativeLower} {$needed} {$possessive} {$translation['ru']}.";
        }

        $ownerDativeLower = mb_strtolower($ownerDative);
        $demonstrative = $this->agreePossessive($translation['gender'], 'этот', 'эта', 'это', 'эти');

        return "У каждого есть {$translation['ru']}, а {$demonstrative} принадлежит {$ownerDativeLower}. Это {$possessive} {$translation['ru']}.";
    }

    private function itsFeedbackTranslation(string $prompt, string $possessivePhrase): string
    {
        preg_match('/ is ([a-z]+)\.$/i', $prompt, $adjectiveMatches);
        $adjective = self::ADJECTIVE_TRANSLATIONS[strtolower($adjectiveMatches[1] ?? '')]['f'] ?? '';

        return match (true) {
            str_contains($prompt, 'has got a toy') => "У животного есть игрушка. {$possessivePhrase} {$adjective}.",
            str_contains($prompt, 'belongs to') => "Эта лежанка принадлежит животному. Это {$possessivePhrase}.",
            str_contains($prompt, 'is eating') => "Животное ест. {$possessivePhrase} находится в миске.",
            str_contains($prompt, 'in the garden') => "Животное находится в саду. {$possessivePhrase} лежит рядом с деревом.",
            default => 'У каждого животного есть место для сна. Животное находится в своей лежанке.',
        };
    }

    private function independentFeedbackTranslation(string $prompt, PossessivePronoun $pronoun): string
    {
        $object = $this->objectFromPrompt($prompt);
        $translation = self::OBJECT_TRANSLATIONS[$object] ?? ['ru' => $object, 'gender' => 'm'];
        $possessive = $this->russianPossessive($pronoun, $translation['gender']);

        $ownerHas = match ($pronoun) {
            PossessivePronoun::mine => 'У меня',
            PossessivePronoun::yours => 'У вас',
            PossessivePronoun::his => 'У мальчика',
            PossessivePronoun::hers => 'У девочки',
            PossessivePronoun::ours => 'У нас',
            PossessivePronoun::theirs => 'У детей',
            default => '',
        };
        $ownerDative = match ($pronoun) {
            PossessivePronoun::mine => 'Мне',
            PossessivePronoun::yours => 'Вам',
            PossessivePronoun::his => 'Ему',
            PossessivePronoun::hers => 'Ей',
            PossessivePronoun::ours => 'Нам',
            PossessivePronoun::theirs => 'Им',
            default => '',
        };
        $demonstrative = $this->agreePossessive(
            $translation['gender'],
            'этот',
            'эта',
            'это',
            'эти',
        ).' '.$translation['ru'];
        $demonstrative = mb_strtoupper(mb_substr($demonstrative, 0, 1)).mb_substr($demonstrative, 1);
        $subjectPronoun = $this->agreePossessive(
            $translation['gender'],
            'он',
            'она',
            'оно',
            'они',
        );

        return match (true) {
            str_contains($prompt, ' have got '), str_contains($prompt, ' has got ') => "{$ownerHas} есть {$translation['ru']}. {$demonstrative} — {$possessive}.",
            str_contains($prompt, ' belongs to ') => "{$ownerDative} принадлежит {$translation['ru']}. {$demonstrative} — {$possessive}.",
            str_starts_with($prompt, 'Here is ') => "Вот {$possessive} {$translation['ru']}. {$demonstrative} — {$possessive}.",
            str_contains($prompt, ' is here.') => mb_strtoupper(mb_substr($possessive, 0, 1)).mb_substr($possessive, 1)
                    ." {$translation['ru']} здесь. {$demonstrative} — {$possessive}.",
            default => "Это {$possessive} {$translation['ru']}? Да, {$subjectPronoun} {$possessive}.",
        };
    }

    private function objectFromPrompt(string $prompt): string
    {
        foreach (array_keys(self::OBJECT_TRANSLATIONS) as $object) {
            if (preg_match('/\\b'.preg_quote($object, '/').'\\b/i', $prompt) === 1) {
                return $object;
            }
        }

        return '';
    }

    private function russianPossessive(PossessivePronoun $pronoun, string $gender): string
    {
        return match ($pronoun) {
            PossessivePronoun::my,
            PossessivePronoun::mine => $this->agreePossessive($gender, 'мой', 'моя', 'моё', 'мои'),
            PossessivePronoun::your,
            PossessivePronoun::yours => $this->agreePossessive($gender, 'ваш', 'ваша', 'ваше', 'ваши'),
            PossessivePronoun::his => 'его',
            PossessivePronoun::her,
            PossessivePronoun::hers => 'её',
            PossessivePronoun::its => 'его или её',
            PossessivePronoun::our,
            PossessivePronoun::ours => $this->agreePossessive($gender, 'наш', 'наша', 'наше', 'наши'),
            PossessivePronoun::their,
            PossessivePronoun::theirs => 'их',
        };
    }

    private function agreePossessive(string $gender, string $masculine, string $feminine, string $neuter, string $plural): string
    {
        return match ($gender) {
            'f' => $feminine,
            'n' => $neuter,
            'p' => $plural,
            default => $masculine,
        };
    }

    /** @return list<array{id: string, label: string}> */
    private function options(): array
    {
        return array_map(
            fn (PossessivePronoun $pronoun): array => ['id' => $pronoun->value, 'label' => $pronoun->value],
            PossessivePronoun::cases(),
        );
    }

    /** @template T @param list<T> $items @return T */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
