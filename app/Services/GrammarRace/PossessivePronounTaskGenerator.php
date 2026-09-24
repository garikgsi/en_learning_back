<?php

namespace App\Services\GrammarRace;

use App\Enums\PossessivePronoun;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\Data\GeneratedGrammarRaceTask;
use InvalidArgumentException;

class PossessivePronounTaskGenerator implements GrammarRaceTaskGenerator
{
    /** @var list<string> */
    private const OBJECTS = ['bag', 'bike', 'book', 'camera', 'computer', 'desk', 'jacket', 'phone', 'room', 'watch'];

    /** @var list<string> */
    private const ADJECTIVES = ['beautiful', 'black', 'blue', 'expensive', 'green', 'new', 'old', 'red', 'small', 'useful'];

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
        };
    }

    private function mySentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "I have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to me. It is ___ {$object}.",
            3 => "I use a {$object} every day. ___ {$object} is {$adjective}.",
            4 => "I left the {$object} at home. I need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to me. It is ___ {$object}.",
        };
    }

    private function yourSentence(int $template, string $object, string $adjective): string
    {
        return match ($template) {
            1 => "You have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to you. It is ___ {$object}.",
            3 => "You use a {$object} every day. ___ {$object} is {$adjective}.",
            4 => "You left the {$object} at school. You need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to you. It is ___ {$object}.",
        };
    }

    private function hisSentence(int $template, string $object, string $adjective): string
    {
        $name = $this->pick(self::MALE_NAMES);

        return match ($template) {
            1 => "{$name} has got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to {$name}. It is ___ {$object}.",
            3 => "{$name} uses a {$object} every day. ___ {$object} is {$adjective}.",
            4 => "My brother left the {$object} at home. He needs ___ {$object} now.",
            default => "The boy has a {$object}, and this one belongs to him. It is ___ {$object}.",
        };
    }

    private function herSentence(int $template, string $object, string $adjective): string
    {
        $name = $this->pick(self::FEMALE_NAMES);

        return match ($template) {
            1 => "{$name} has got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to {$name}. It is ___ {$object}.",
            3 => "{$name} uses a {$object} every day. ___ {$object} is {$adjective}.",
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
            3 => "We use a {$object} every day. ___ {$object} is {$adjective}.",
            4 => "My brother and I left the {$object} at home. We need ___ {$object} now.",
            default => "Everyone has a {$object}, and this one belongs to us. It is ___ {$object}.",
        };
    }

    private function theirSentence(int $template, string $object, string $adjective): string
    {
        $first = $this->pick(self::MALE_NAMES);
        $second = $this->pick(self::FEMALE_NAMES);

        return match ($template) {
            1 => "{$first} and {$second} have got a {$object}. ___ {$object} is {$adjective}.",
            2 => "This {$object} belongs to {$first} and {$second}. It is ___ {$object}.",
            3 => "The students use a {$object} every day. ___ {$object} is {$adjective}.",
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
            PossessivePronoun::his => 'Собственник — мальчик или мужчина (he), поэтому используем his.',
            PossessivePronoun::her => 'Собственник — девочка или женщина (she), поэтому используем her.',
            PossessivePronoun::its => 'Собственник — животное или предмет (it), поэтому используем its.',
            PossessivePronoun::our => 'Предмет принадлежит нам (we), поэтому используем our.',
            PossessivePronoun::their => 'Предмет принадлежит нескольким людям, животным или предметам (they), поэтому используем their.',
        };
    }

    private function feedbackTranslation(string $prompt, PossessivePronoun $pronoun): string
    {
        preg_match('/___\s+([a-z]+)/i', $prompt, $matches);
        $object = strtolower($matches[1] ?? '');
        $translation = self::OBJECT_TRANSLATIONS[$object] ?? ['ru' => $object, 'gender' => 'm'];
        $possessive = match ($pronoun) {
            PossessivePronoun::my => $this->agreePossessive($translation['gender'], 'мой', 'моя', 'моё', 'мои'),
            PossessivePronoun::your => $this->agreePossessive($translation['gender'], 'твой', 'твоя', 'твоё', 'твои'),
            PossessivePronoun::his => 'его',
            PossessivePronoun::her => 'её',
            PossessivePronoun::its => 'его / её',
            PossessivePronoun::our => $this->agreePossessive($translation['gender'], 'наш', 'наша', 'наше', 'наши'),
            PossessivePronoun::their => 'их',
        };

        $text = "{$possessive} {$translation['ru']}";

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
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
