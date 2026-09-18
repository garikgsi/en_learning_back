<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonetizationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coins' => $this->coins,
            'rublesPerCoin' => $this->kopecks_per_coin / 100,
            'amountRubles' => $this->coins * $this->kopecks_per_coin / 100,
            'createdAt' => $this->created_at->toISOString(),
            'processedAt' => $this->processed_at?->toISOString(),
            'isProcessed' => $this->processed_at !== null,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'balance' => (int) ($this->user->enc_balance ?? 0),
                'reserved' => (int) ($this->user->enc_reserved ?? 0),
            ]),
        ];
    }
}
