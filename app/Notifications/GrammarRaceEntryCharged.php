<?php

namespace App\Notifications;

use App\Models\EnCoinEntry;
use App\Models\GrammarRaceSession;
use App\Notifications\Channels\NotificationJournalChannel;
use App\Notifications\Contracts\SkipsPushDelivery;
use App\Notifications\Contracts\StoresInNotificationJournal;
use App\Services\GrammarRace\GrammarRaceGameRegistry;
use Illuminate\Notifications\Notification;

class GrammarRaceEntryCharged extends Notification implements SkipsPushDelivery, StoresInNotificationJournal
{
    public function __construct(
        private readonly EnCoinEntry $entry,
        private readonly int $balance,
        private readonly GrammarRaceSession $session,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [NotificationJournalChannel::class];
    }

    public function deduplicationKey(object $notifiable): string
    {
        return "encoin:entry:{$this->entry->id}";
    }

    /** @return array{type: string, title: string, body: string, data: array<string, scalar>} */
    public function toJournal(object $notifiable): array
    {
        $coins = abs($this->entry->amount);

        return [
            'type' => 'grammar_race.entry_charged',
            'title' => 'Оплачена игровая попытка',
            'body' => "Списано {$coins} EnCoin за грамматическую гонку. Баланс: {$this->balance} EnCoin.",
            'data' => [
                'route' => app(GrammarRaceGameRegistry::class)
                    ->get($this->session->game_code)
                    ->route(),
                'entryId' => $this->entry->id,
                'amount' => $this->entry->amount,
                'balance' => $this->balance,
                'reason' => $this->entry->reason,
                'grammarRaceSessionId' => $this->entry->grammar_race_session_id,
            ],
        ];
    }
}
