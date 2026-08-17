<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordResource extends JsonResource
{
    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ru' => $this->ru,
            'en' => $this->en,
            'grade' => $this->grade,
            'createdAt' => $this->created_at->toISOString(),
            'repeatCount' => (int) ($this->repeat_count ?? 0),
            'successfulRepeatCount' => (int) ($this->successful_repeat_count ?? 0),
            'failedRepeatCount' => (int) ($this->failed_repeat_count ?? 0),
            'is_active' => (bool) ($this->is_active ?? false),
        ];
    }
}
