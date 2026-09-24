<?php

namespace App\Services\GrammarRace\Data;

final readonly class GeneratedGrammarRaceTask
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{id: string, label: string}>  $options
     */
    public function __construct(
        public string $type,
        public array $payload,
        public array $options,
        public string $correctAnswer,
        public string $botAnswer,
        public int $botDelayMs,
    ) {}

    /** @return array<string, mixed> */
    public function toPersistenceArray(int $position): array
    {
        return [
            'position' => $position,
            'task_type' => $this->type,
            'payload' => $this->payload,
            'options' => $this->options,
            // Kept for compatibility with sessions created before typed task payloads.
            'prompt' => (string) ($this->payload['text'] ?? $this->payload['question'] ?? ''),
            'translation' => $this->payload['translation'] ?? null,
            'correct_answer' => $this->correctAnswer,
            'bot_answer' => $this->botAnswer,
            'bot_delay_ms' => $this->botDelayMs,
        ];
    }
}
