<?php

namespace App\Services\GrammarRace;

use App\Enums\GrammarRaceTaskMode;
use App\Enums\PersonalPronoun;
use App\Services\GrammarRace\Data\PersonalPronounLexicon;
use InvalidArgumentException;

class PersonalPronounTaskGenerator
{
    /**
     * @return list<array{
     *     position: int,
     *     prompt: string,
     *     translation: string|null,
     *     correct_answer: string,
     *     bot_answer: string,
     *     bot_delay_ms: int
     * }>
     */
    public function generate(int $difficultyLevel, int $count): array
    {
        $settings = config("grammar_race.levels.{$difficultyLevel}");

        if (! is_array($settings)) {
            throw new InvalidArgumentException("Unknown grammar race level {$difficultyLevel}.");
        }

        $pronouns = $this->balancedPronouns($count);
        $tasks = [];
        $usedPrompts = [];

        foreach ($pronouns as $index => $pronoun) {
            $task = null;

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $task = $settings['mode'] === GrammarRaceTaskMode::phrase->value
                    ? $this->phrase($pronoun, (int) $settings['content_level'], (bool) $settings['show_translation'])
                    : $this->sentence($pronoun, (int) $settings['content_level']);

                if (! isset($usedPrompts[$task['prompt']])) {
                    break;
                }
            }

            $usedPrompts[$task['prompt']] = true;
            $tasks[] = [
                'position' => $index + 1,
                ...$task,
                'correct_answer' => $pronoun->value,
                'bot_answer' => $this->botAnswer($pronoun, (int) $settings['bot_error_percent'])->value,
                'bot_delay_ms' => random_int(
                    (int) $settings['bot_min_delay_ms'],
                    (int) $settings['bot_max_delay_ms'],
                ),
            ];
        }

        return $tasks;
    }

    /** @return list<PersonalPronoun> */
    private function balancedPronouns(int $count): array
    {
        $result = [];
        $previous = null;

        while (count($result) < $count) {
            $cycle = PersonalPronoun::cases();
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

    /** @return array{prompt: string, translation: string|null} */
    private function phrase(PersonalPronoun $pronoun, int $level, bool $withTranslation): array
    {
        return match ($pronoun) {
            PersonalPronoun::he => $this->personPhrase(true, $level, $withTranslation),
            PersonalPronoun::she => $this->personPhrase(false, $level, $withTranslation),
            PersonalPronoun::it => $this->objectPhrase($withTranslation),
            PersonalPronoun::we => $this->wePhrase($level, $withTranslation),
            PersonalPronoun::they => $this->theyPhrase($level, $withTranslation),
        };
    }

    /** @return array{prompt: string, translation: string|null} */
    private function personPhrase(bool $male, int $level, bool $withTranslation): array
    {
        $relations = $male
            ? PersonalPronounLexicon::maleRelations()
            : PersonalPronounLexicon::femaleRelations();

        if ($withTranslation || $level <= 2 || random_int(1, 3) === 1) {
            $relation = $this->pick($relations);

            return ['prompt' => $relation['en'], 'translation' => $withTranslation ? $relation['ru'] : null];
        }

        if ($level >= 4 && random_int(0, 1) === 1) {
            $title = $male ? 'Mr' : 'Ms';

            return [
                'prompt' => $title.' '.$this->pick(PersonalPronounLexicon::surnames()),
                'translation' => null,
            ];
        }

        return [
            'prompt' => $this->pick($male ? PersonalPronounLexicon::maleNames() : PersonalPronounLexicon::femaleNames()),
            'translation' => null,
        ];
    }

    /** @return array{prompt: string, translation: string|null} */
    private function objectPhrase(bool $withTranslation): array
    {
        if (random_int(1, 8) === 1) {
            $nature = $this->pick(PersonalPronounLexicon::singularNature());

            return [
                'prompt' => $nature['en'],
                'translation' => $withTranslation ? $nature['ru'] : null,
            ];
        }

        $object = $this->pick(PersonalPronounLexicon::objects());
        $determiner = $this->pick(['My', 'His', 'Her', 'The']);
        $translation = null;

        if ($withTranslation) {
            $russianDeterminer = match ($determiner) {
                'My' => match ($object['gender']) {
                    'm' => 'Мой',
                    'f' => 'Моя',
                    default => 'Моё',
                },
                'His' => 'Его',
                'Her' => 'Её',
                default => '',
            };
            $translation = trim($russianDeterminer.' '.$object['ru']);
            $translation = $this->upperFirst($translation);
        }

        return [
            'prompt' => $determiner.' '.$object['en'],
            'translation' => $translation,
        ];
    }

    /** @return array{prompt: string, translation: string|null} */
    private function wePhrase(int $level, bool $withTranslation): array
    {
        $relations = [
            ['en' => 'My sister and I', 'ru' => 'Мы с моей сестрой'],
            ['en' => 'My brother and I', 'ru' => 'Мы с моим братом'],
            ['en' => 'My friend and I', 'ru' => 'Мы с моим другом'],
            ['en' => 'My friends and I', 'ru' => 'Мы с моими друзьями'],
            ['en' => 'Mum and I', 'ru' => 'Мы с мамой'],
            ['en' => 'Dad and I', 'ru' => 'Мы с папой'],
        ];

        if ($withTranslation || $level <= 2 || random_int(0, 1) === 0) {
            $relation = $this->pick($relations);

            return ['prompt' => $relation['en'], 'translation' => $withTranslation ? $relation['ru'] : null];
        }

        return [
            'prompt' => $this->pick(array_merge(
                PersonalPronounLexicon::maleNames(),
                PersonalPronounLexicon::femaleNames(),
            )).' and I',
            'translation' => null,
        ];
    }

    /** @return array{prompt: string, translation: string|null} */
    private function theyPhrase(int $level, bool $withTranslation): array
    {
        $groups = [
            ['en' => 'My friends', 'ru' => 'Мои друзья'],
            ['en' => 'My mum and dad', 'ru' => 'Мои мама и папа'],
            ['en' => 'My parents', 'ru' => 'Мои родители'],
            ['en' => 'The boys and girls', 'ru' => 'Мальчики и девочки'],
        ];

        if ($withTranslation) {
            $kind = random_int(1, 3);

            if ($kind === 1) {
                $group = $this->pick($groups);

                return ['prompt' => $group['en'], 'translation' => $group['ru']];
            }

            if ($kind === 2) {
                $names = PersonalPronounLexicon::beginnerNameTranslations();
                $english = array_keys($names);
                $first = $this->pick($english);
                $second = $this->pick(array_values(array_diff($english, [$first])));

                return [
                    'prompt' => "{$first} and {$second}",
                    'translation' => "{$names[$first]} и {$names[$second]}",
                ];
            }

            $object = $this->pick(PersonalPronounLexicon::objects());

            return [
                'prompt' => 'The '.$object['plural'],
                'translation' => $this->upperFirst($object['ruPlural']),
            ];
        }

        if (random_int(1, 3) === 1) {
            $group = $this->pick($groups);

            return ['prompt' => $group['en'], 'translation' => null];
        }

        if ($level >= 3 && random_int(0, 1) === 1) {
            $object = $this->pick(PersonalPronounLexicon::objects());

            return ['prompt' => 'The '.$object['plural'], 'translation' => null];
        }

        $names = array_merge(PersonalPronounLexicon::maleNames(), PersonalPronounLexicon::femaleNames());
        $first = $this->pick($names);
        $second = $this->pick(array_values(array_diff($names, [$first])));

        return ['prompt' => "{$first} and {$second}", 'translation' => null];
    }

    /** @return array{prompt: string, translation: null} */
    private function sentence(PersonalPronoun $pronoun, int $level): array
    {
        $template = random_int(1, min(5, max(1, (int) ceil($level / 2))));
        $prompt = match ($pronoun) {
            PersonalPronoun::he => $this->heSentence($template),
            PersonalPronoun::she => $this->sheSentence($template),
            PersonalPronoun::it => $this->itSentence($template),
            PersonalPronoun::we => $this->weSentence($template),
            PersonalPronoun::they => $this->theySentence($template),
        };

        return ['prompt' => $prompt, 'translation' => null];
    }

    private function heSentence(int $template): string
    {
        $name = $this->pick(PersonalPronounLexicon::maleNames());

        return match ($template) {
            1 => "{$name} is my brother. ___ is kind.",
            2 => 'This is Mr '.$this->pick(PersonalPronounLexicon::surnames()).'. ___ is a teacher.',
            3 => "{$name} has got a car. ___ is my brother.",
            4 => 'My father is a doctor. ___ works at a hospital.',
            default => "{$name} likes music. ___ is my friend.",
        };
    }

    private function sheSentence(int $template): string
    {
        $name = $this->pick(PersonalPronounLexicon::femaleNames());

        return match ($template) {
            1 => "{$name} is my sister. ___ is kind.",
            2 => 'This is Ms '.$this->pick(PersonalPronounLexicon::surnames()).'. ___ is a teacher.',
            3 => "{$name} has got a bike. ___ is my sister.",
            4 => 'My mother is a doctor. ___ works at a hospital.',
            default => "{$name} likes music. ___ is my friend.",
        };
    }

    private function itSentence(int $template): string
    {
        $object = $this->pick(PersonalPronounLexicon::objects());
        $adjective = $this->pick(['black', 'blue', 'green', 'new', 'small']);

        return match ($template) {
            1 => "This is my {$object['en']}. ___ is {$adjective}.",
            2 => "I have got a {$object['en']}. ___ is {$adjective}.",
            3 => 'Kate has got a '.$object['en'].". ___ is {$adjective}.",
            4 => 'The '.$object['en']." costs ten pounds. ___ is {$adjective}.",
            default => 'My brother likes this '.$object['en'].". ___ is {$adjective}.",
        };
    }

    private function weSentence(int $template): string
    {
        $name = $this->pick(array_merge(PersonalPronounLexicon::maleNames(), PersonalPronounLexicon::femaleNames()));

        return match ($template) {
            1 => "{$name} and I are friends. ___ are classmates.",
            2 => 'My sister and I are students. ___ are classmates.',
            3 => 'My brother and I have got two bikes. ___ are happy.',
            4 => "{$name} and I play football. ___ are a team.",
            default => "{$name} and I live in London. ___ are neighbours.",
        };
    }

    private function theySentence(int $template): string
    {
        $names = array_merge(PersonalPronounLexicon::maleNames(), PersonalPronounLexicon::femaleNames());
        $first = $this->pick($names);
        $second = $this->pick(array_values(array_diff($names, [$first])));
        $object = $this->pick(PersonalPronounLexicon::objects());

        return match ($template) {
            1 => "{$first} and {$second} are friends. ___ are classmates.",
            2 => "These are my {$object['plural']}. ___ are new.",
            3 => "{$first} and {$second} have got two bikes. ___ are happy.",
            4 => 'My parents work at a school. ___ are teachers.',
            default => "The {$object['plural']} are in the room. ___ are small.",
        };
    }

    private function botAnswer(PersonalPronoun $correct, int $errorPercent): PersonalPronoun
    {
        if (random_int(1, 10000) > $errorPercent * 100) {
            return $correct;
        }

        return $this->pick(array_values(array_filter(
            PersonalPronoun::cases(),
            fn (PersonalPronoun $pronoun): bool => $pronoun !== $correct,
        )));
    }

    /** @template T @param list<T> $items @return T */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function upperFirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
