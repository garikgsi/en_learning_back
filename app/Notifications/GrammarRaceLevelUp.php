<?php

namespace App\Notifications;

use App\Enums\GrammarRaceGameCode;
use App\Notifications\Channels\NotificationJournalChannel;
use App\Notifications\Contracts\StoresInNotificationJournal;
use Illuminate\Notifications\Notification;

class GrammarRaceLevelUp extends Notification implements StoresInNotificationJournal
{
    public function __construct(
        private readonly GrammarRaceGameCode $gameCode,
        private readonly string $gameTitle,
        private readonly string $rankTitle,
        private readonly int $level,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [NotificationJournalChannel::class];
    }

    public function deduplicationKey(object $notifiable): string
    {
        return "grammar-race:level-up:{$this->gameCode->value}:{$this->level}";
    }

    /** @return array{type: string, title: string, body: string, data: array<string, scalar>} */
    public function toJournal(object $notifiable): array
    {
        return [
            'type' => 'grammar_race.level_up',
            'title' => 'Новый уровень в игре',
            'body' => "Теперь ваш уровень в игре «{$this->gameTitle}» повышен до «{$this->rankTitle} {$this->level} уровня».",
            'data' => [
                'route' => '/achievements',
                'gameCode' => $this->gameCode->value,
                'gameTitle' => $this->gameTitle,
                'rankTitle' => $this->rankTitle,
                'level' => $this->level,
            ],
        ];
    }
}
