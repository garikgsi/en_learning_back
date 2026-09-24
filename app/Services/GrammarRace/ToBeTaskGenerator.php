<?php

namespace App\Services\GrammarRace;

use App\Enums\ToBeForm;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\Data\GeneratedGrammarRaceTask;
use InvalidArgumentException;

class ToBeTaskGenerator implements GrammarRaceTaskGenerator
{
    /**
     * @param  array<string, mixed>  $level
     * @return list<GeneratedGrammarRaceTask>
     */
    public function generate(array $level, int $count, float $reactionMultiplier = 1): array
    {
        if (! isset(
            $level['content_level'],
            $level['bot_error_percent'],
            $level['bot_min_delay_ms'],
            $level['bot_max_delay_ms'],
        )) {
            throw new InvalidArgumentException('Incomplete to-be level configuration.');
        }

        $contentLevel = (int) $level['content_level'];
        $forms = $this->formsForLevel($contentLevel);
        $options = array_map(
            fn (string $form): array => ['id' => $form, 'label' => $form],
            $forms,
        );
        $tasks = [];

        foreach ($this->balancedCandidates($this->candidates($contentLevel), $forms, $count) as $candidate) {
            $correct = $candidate['answer'];
            $tasks[] = new GeneratedGrammarRaceTask(
                type: 'single_choice',
                payload: [
                    'text' => $candidate['text'],
                    'translation' => $candidate['translation'],
                    'instruction' => 'Выберите правильную форму глагола to be',
                    'feedback' => [
                        'correctText' => $this->completedText($candidate['text'], $correct),
                        'translation' => $candidate['translation'],
                        'explanation' => $this->explanation(ToBeForm::from($correct)),
                    ],
                ],
                options: $options,
                correctAnswer: $correct,
                botAnswer: $this->botAnswer($correct, $forms, (int) $level['bot_error_percent']),
                botDelayMs: random_int(
                    (int) round($level['bot_min_delay_ms'] * $reactionMultiplier),
                    (int) round($level['bot_max_delay_ms'] * $reactionMultiplier),
                ),
            );
        }

        return $tasks;
    }

    /** @return list<string> */
    private function formsForLevel(int $level): array
    {
        $forms = $level < 6
            ? [ToBeForm::am, ToBeForm::is, ToBeForm::are]
            : ToBeForm::cases();

        return array_map(fn (ToBeForm $form): string => $form->value, $forms);
    }

    /**
     * @return list<array{text: string, translation: string, answer: string}>
     */
    private function candidates(int $level): array
    {
        return match ($level) {
            1 => $this->presentPronouns(),
            2 => [...$this->presentPronouns(), ...$this->presentDescriptions()],
            3 => $this->presentNouns(),
            4 => [...$this->presentNouns(), ...$this->presentGroups()],
            5 => $this->presentQuestionsAndNegatives(),
            6 => [...$this->presentPronouns(), ...$this->pastPronouns()],
            7 => [...$this->presentNouns(), ...$this->pastNouns()],
            8 => [...$this->presentQuestionsAndNegatives(), ...$this->pastNegatives()],
            9 => [...$this->presentQuestionsAndNegatives(), ...$this->pastQuestions()],
            10 => [
                ...$this->presentNouns(),
                ...$this->presentGroups(),
                ...$this->presentQuestionsAndNegatives(),
                ...$this->pastNouns(),
                ...$this->pastNegatives(),
                ...$this->pastQuestions(),
            ],
            default => throw new InvalidArgumentException("Unsupported to-be content level: {$level}."),
        };
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function presentPronouns(): array
    {
        return [
            ['text' => 'I ___ at school today.', 'translation' => 'Я сегодня в школе.', 'answer' => 'am'],
            ['text' => 'I ___ ready now.', 'translation' => 'Я сейчас готов.', 'answer' => 'am'],
            ['text' => 'I ___ happy today.', 'translation' => 'Я сегодня счастлив.', 'answer' => 'am'],
            ['text' => 'He ___ at home now.', 'translation' => 'Он сейчас дома.', 'answer' => 'is'],
            ['text' => 'She ___ in class today.', 'translation' => 'Она сегодня на уроке.', 'answer' => 'is'],
            ['text' => 'It ___ cold today.', 'translation' => 'Сегодня холодно.', 'answer' => 'is'],
            ['text' => 'He ___ busy now.', 'translation' => 'Он сейчас занят.', 'answer' => 'is'],
            ['text' => 'She ___ happy today.', 'translation' => 'Она сегодня счастлива.', 'answer' => 'is'],
            ['text' => 'You ___ at school today.', 'translation' => 'Ты сегодня в школе.', 'answer' => 'are'],
            ['text' => 'We ___ ready now.', 'translation' => 'Мы сейчас готовы.', 'answer' => 'are'],
            ['text' => 'They ___ at home today.', 'translation' => 'Они сегодня дома.', 'answer' => 'are'],
            ['text' => 'You ___ busy now.', 'translation' => 'Вы сейчас заняты.', 'answer' => 'are'],
            ['text' => 'We ___ happy today.', 'translation' => 'Мы сегодня счастливы.', 'answer' => 'are'],
            ['text' => 'They ___ in class now.', 'translation' => 'Они сейчас на уроке.', 'answer' => 'are'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function presentDescriptions(): array
    {
        return [
            ['text' => 'I ___ eleven years old today.', 'translation' => 'Мне сегодня одиннадцать лет.', 'answer' => 'am'],
            ['text' => 'I ___ the team captain today.', 'translation' => 'Сегодня я капитан команды.', 'answer' => 'am'],
            ['text' => 'He ___ very kind today.', 'translation' => 'Сегодня он очень добрый.', 'answer' => 'is'],
            ['text' => 'She ___ my best friend today.', 'translation' => 'Сегодня она моя лучшая подруга.', 'answer' => 'is'],
            ['text' => 'It ___ sunny outside now.', 'translation' => 'Сейчас на улице солнечно.', 'answer' => 'is'],
            ['text' => 'You ___ very helpful today.', 'translation' => 'Сегодня ты очень помогаешь.', 'answer' => 'are'],
            ['text' => 'We ___ a good team today.', 'translation' => 'Сегодня мы хорошая команда.', 'answer' => 'are'],
            ['text' => 'They ___ very quiet now.', 'translation' => 'Сейчас они ведут себя очень тихо.', 'answer' => 'are'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function presentNouns(): array
    {
        return [
            ['text' => 'I ___ the class monitor today.', 'translation' => 'Сегодня я дежурный по классу.', 'answer' => 'am'],
            ['text' => 'I ___ in the library now.', 'translation' => 'Я сейчас в библиотеке.', 'answer' => 'am'],
            ['text' => 'Anna ___ at school today.', 'translation' => 'Анна сегодня в школе.', 'answer' => 'is'],
            ['text' => 'My brother ___ at home now.', 'translation' => 'Мой брат сейчас дома.', 'answer' => 'is'],
            ['text' => 'The cat ___ under the table now.', 'translation' => 'Кошка сейчас под столом.', 'answer' => 'is'],
            ['text' => 'This book ___ interesting today.', 'translation' => 'Сегодня эта книга кажется интересной.', 'answer' => 'is'],
            ['text' => 'Max ___ our goalkeeper today.', 'translation' => 'Сегодня Макс наш вратарь.', 'answer' => 'is'],
            ['text' => 'The children ___ in the park now.', 'translation' => 'Дети сейчас в парке.', 'answer' => 'are'],
            ['text' => 'My parents ___ at work today.', 'translation' => 'Мои родители сегодня на работе.', 'answer' => 'are'],
            ['text' => 'Anna and Max ___ at school today.', 'translation' => 'Анна и Макс сегодня в школе.', 'answer' => 'are'],
            ['text' => 'The books ___ on the desk now.', 'translation' => 'Книги сейчас на парте.', 'answer' => 'are'],
            ['text' => 'Our friends ___ here today.', 'translation' => 'Наши друзья сегодня здесь.', 'answer' => 'are'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function presentGroups(): array
    {
        return [
            ['text' => 'I ___ with my classmates now.', 'translation' => 'Я сейчас со своими одноклассниками.', 'answer' => 'am'],
            ['text' => 'I ___ the only pupil here today.', 'translation' => 'Сегодня я здесь единственный ученик.', 'answer' => 'am'],
            ['text' => 'My mother ___ with us today.', 'translation' => 'Моя мама сегодня с нами.', 'answer' => 'is'],
            ['text' => 'The red pencil ___ on the desk now.', 'translation' => 'Красный карандаш сейчас на парте.', 'answer' => 'is'],
            ['text' => 'Tom and I ___ partners today.', 'translation' => 'Сегодня мы с Томом напарники.', 'answer' => 'are'],
            ['text' => 'My sister and brother ___ at home now.', 'translation' => 'Мои сестра и брат сейчас дома.', 'answer' => 'are'],
            ['text' => 'The dog and the cat ___ outside now.', 'translation' => 'Собака и кошка сейчас на улице.', 'answer' => 'are'],
            ['text' => 'You and Kate ___ in one team today.', 'translation' => 'Сегодня вы с Катей в одной команде.', 'answer' => 'are'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function presentQuestionsAndNegatives(): array
    {
        return [
            ['text' => 'I ___ not tired today.', 'translation' => 'Я сегодня не устал.', 'answer' => 'am'],
            ['text' => '___ I in the right classroom now?', 'translation' => 'Я сейчас в нужном кабинете?', 'answer' => 'am'],
            ['text' => 'I ___ not at home now.', 'translation' => 'Я сейчас не дома.', 'answer' => 'am'],
            ['text' => '___ I your partner today?', 'translation' => 'Я сегодня твой напарник?', 'answer' => 'am'],
            ['text' => 'He ___ not busy today.', 'translation' => 'Он сегодня не занят.', 'answer' => 'is'],
            ['text' => '___ she at school now?', 'translation' => 'Она сейчас в школе?', 'answer' => 'is'],
            ['text' => 'The shop ___ not open today.', 'translation' => 'Магазин сегодня не открыт.', 'answer' => 'is'],
            ['text' => '___ it cold outside now?', 'translation' => 'Сейчас на улице холодно?', 'answer' => 'is'],
            ['text' => 'We ___ not late today.', 'translation' => 'Мы сегодня не опоздали.', 'answer' => 'are'],
            ['text' => '___ you ready now?', 'translation' => 'Ты сейчас готов?', 'answer' => 'are'],
            ['text' => 'They ___ not at home today.', 'translation' => 'Они сегодня не дома.', 'answer' => 'are'],
            ['text' => '___ the children in class now?', 'translation' => 'Дети сейчас на уроке?', 'answer' => 'are'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function pastPronouns(): array
    {
        return [
            ['text' => 'I ___ at school yesterday.', 'translation' => 'Я был в школе вчера.', 'answer' => 'was'],
            ['text' => 'He ___ at home last night.', 'translation' => 'Он был дома прошлой ночью.', 'answer' => 'was'],
            ['text' => 'She ___ happy yesterday.', 'translation' => 'Она была счастлива вчера.', 'answer' => 'was'],
            ['text' => 'It ___ cold yesterday.', 'translation' => 'Вчера было холодно.', 'answer' => 'was'],
            ['text' => 'I ___ busy two days ago.', 'translation' => 'Я был занят два дня назад.', 'answer' => 'was'],
            ['text' => 'You ___ at school yesterday.', 'translation' => 'Ты был в школе вчера.', 'answer' => 'were'],
            ['text' => 'We ___ ready last Monday.', 'translation' => 'Мы были готовы в прошлый понедельник.', 'answer' => 'were'],
            ['text' => 'They ___ at home last night.', 'translation' => 'Они были дома прошлой ночью.', 'answer' => 'were'],
            ['text' => 'You ___ happy two days ago.', 'translation' => 'Вы были счастливы два дня назад.', 'answer' => 'were'],
            ['text' => 'We ___ in class yesterday.', 'translation' => 'Мы были на уроке вчера.', 'answer' => 'were'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function pastNouns(): array
    {
        return [
            ['text' => 'Anna ___ at school yesterday.', 'translation' => 'Анна была в школе вчера.', 'answer' => 'was'],
            ['text' => 'My brother ___ at home last night.', 'translation' => 'Мой брат был дома прошлой ночью.', 'answer' => 'was'],
            ['text' => 'The cat ___ under the table yesterday.', 'translation' => 'Кошка была под столом вчера.', 'answer' => 'was'],
            ['text' => 'The weather ___ warm last Sunday.', 'translation' => 'Погода была тёплой в прошлое воскресенье.', 'answer' => 'was'],
            ['text' => 'Max ___ our captain last year.', 'translation' => 'Макс был нашим капитаном в прошлом году.', 'answer' => 'was'],
            ['text' => 'The children ___ in the park yesterday.', 'translation' => 'Дети были в парке вчера.', 'answer' => 'were'],
            ['text' => 'My parents ___ at work last Monday.', 'translation' => 'Мои родители были на работе в прошлый понедельник.', 'answer' => 'were'],
            ['text' => 'Anna and Max ___ at school yesterday.', 'translation' => 'Анна и Макс были в школе вчера.', 'answer' => 'were'],
            ['text' => 'The books ___ on the desk last night.', 'translation' => 'Книги были на столе прошлой ночью.', 'answer' => 'were'],
            ['text' => 'Our friends ___ here two days ago.', 'translation' => 'Наши друзья были здесь два дня назад.', 'answer' => 'were'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function pastNegatives(): array
    {
        return [
            ['text' => 'I ___ not tired yesterday.', 'translation' => 'Я не был уставшим вчера.', 'answer' => 'was'],
            ['text' => 'He ___ not busy last night.', 'translation' => 'Он не был занят прошлой ночью.', 'answer' => 'was'],
            ['text' => 'The shop ___ not open yesterday.', 'translation' => 'Магазин не был открыт вчера.', 'answer' => 'was'],
            ['text' => 'She ___ not at school last Monday.', 'translation' => 'Она не была в школе в прошлый понедельник.', 'answer' => 'was'],
            ['text' => 'We ___ not late yesterday.', 'translation' => 'Мы не опоздали вчера.', 'answer' => 'were'],
            ['text' => 'They ___ not at home last night.', 'translation' => 'Они не были дома прошлой ночью.', 'answer' => 'were'],
            ['text' => 'The children ___ not noisy yesterday.', 'translation' => 'Дети не шумели вчера.', 'answer' => 'were'],
            ['text' => 'You ___ not in class two days ago.', 'translation' => 'Вас не было на уроке два дня назад.', 'answer' => 'were'],
        ];
    }

    /** @return list<array{text: string, translation: string, answer: string}> */
    private function pastQuestions(): array
    {
        return [
            ['text' => '___ I late yesterday?', 'translation' => 'Я опоздал вчера?', 'answer' => 'was'],
            ['text' => '___ he at home last night?', 'translation' => 'Он был дома прошлой ночью?', 'answer' => 'was'],
            ['text' => '___ Anna at school yesterday?', 'translation' => 'Анна была в школе вчера?', 'answer' => 'was'],
            ['text' => '___ it cold last Monday?', 'translation' => 'В прошлый понедельник было холодно?', 'answer' => 'was'],
            ['text' => '___ you ready yesterday?', 'translation' => 'Ты был готов вчера?', 'answer' => 'were'],
            ['text' => '___ we in this room last night?', 'translation' => 'Мы были в этой комнате прошлой ночью?', 'answer' => 'were'],
            ['text' => '___ they at school two days ago?', 'translation' => 'Они были в школе два дня назад?', 'answer' => 'were'],
            ['text' => '___ the children happy yesterday?', 'translation' => 'Дети были счастливы вчера?', 'answer' => 'were'],
        ];
    }

    /**
     * @param  list<array{text: string, translation: string, answer: string}>  $candidates
     * @param  list<string>  $forms
     * @return list<array{text: string, translation: string, answer: string}>
     */
    private function balancedCandidates(array $candidates, array $forms, int $count): array
    {
        $groups = [];
        foreach ($forms as $form) {
            $groups[$form] = array_values(array_filter(
                $candidates,
                fn (array $candidate): bool => $candidate['answer'] === $form,
            ));
            if ($groups[$form] === []) {
                throw new InvalidArgumentException("No to-be candidates for form {$form}.");
            }
        }

        $queues = $groups;
        foreach ($queues as &$queue) {
            shuffle($queue);
        }
        unset($queue);

        $result = [];
        $previous = null;
        while (count($result) < $count) {
            $cycle = $forms;
            do {
                shuffle($cycle);
            } while (count($cycle) > 1 && $cycle[0] === $previous);

            foreach ($cycle as $form) {
                if (count($result) >= $count) {
                    break;
                }
                if ($queues[$form] === []) {
                    $queues[$form] = $groups[$form];
                    shuffle($queues[$form]);
                }

                $result[] = array_pop($queues[$form]);
                $previous = $form;
            }
        }

        return $result;
    }

    /** @param list<string> $forms */
    private function botAnswer(string $correct, array $forms, int $errorPercent): string
    {
        if (random_int(1, 100) > $errorPercent) {
            return $correct;
        }

        $wrong = array_values(array_filter($forms, fn (string $form): bool => $form !== $correct));

        return $wrong[array_rand($wrong)];
    }

    private function completedText(string $prompt, string $answer): string
    {
        $completed = str_replace('___', $answer, $prompt);

        return str_starts_with($prompt, '___') ? ucfirst($completed) : $completed;
    }

    private function explanation(ToBeForm $form): string
    {
        return match ($form) {
            ToBeForm::am => 'В настоящем времени с I используем форму am.',
            ToBeForm::is => 'В настоящем времени с he, she, it, именем или одним предметом используем форму is.',
            ToBeForm::are => 'В настоящем времени с you, we, they и несколькими предметами используем форму are.',
            ToBeForm::was => 'В прошедшем времени с I, he, she, it, именем или одним предметом используем форму was.',
            ToBeForm::were => 'В прошедшем времени с you, we, they и несколькими предметами используем форму were.',
        };
    }
}
