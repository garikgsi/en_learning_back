<?php

namespace App\Notifications;

use App\Models\EnCoinEntry;
use App\Notifications\Channels\NotificationJournalChannel;
use App\Notifications\Contracts\StoresInNotificationJournal;
use Illuminate\Notifications\Notification;

class EnCoinBalanceChanged extends Notification implements StoresInNotificationJournal
{
    public function __construct(
        private readonly EnCoinEntry $entry,
        private readonly int $balance,
        private readonly ?int $monetizationRequestId = null,
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
        $withdrawal = $this->entry->amount < 0;
        $coins = abs($this->entry->amount);
        $rubles = $coins * $this->entry->kopecks_per_coin / 100;
        $reason = match ($this->entry->reason) {
            'daily' => 'за ежедневное задание',
            'weekly' => 'за еженедельное задание',
            'weekly_bonus' => 'за выполнение всех заданий недели',
            default => '',
        };
        $body = $withdrawal
            ? "Списано {$coins} EnCoin. ".number_format($rubles, 2, ',', ' ').' ₽ зачислены на карту.'
            : "+{$coins} EnCoin {$reason}.";
        $data = [
            'route' => '/balance',
            'entryId' => $this->entry->id,
            'amount' => $this->entry->amount,
            'amountRubles' => $rubles,
            'balance' => $this->balance,
            'reason' => $this->entry->reason,
        ];
        if ($this->entry->exercise_id !== null) {
            $data['exerciseId'] = (int) $this->entry->exercise_id;
        }
        if ($this->monetizationRequestId !== null) {
            $data['monetizationRequestId'] = $this->monetizationRequestId;
        }

        return [
            'type' => $withdrawal ? 'encoin.withdrawal.processed' : 'encoin.credited',
            'title' => $withdrawal ? 'Вывод EnCoin выполнен' : 'Начислены EnCoin',
            'body' => $body." Баланс: {$this->balance} EnCoin.",
            'data' => $data,
        ];
    }
}
