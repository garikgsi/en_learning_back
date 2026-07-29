<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'type' => [
                'id' => $this->type->id,
                'name' => $this->type->name,
                'title' => $this->type->title,
            ],
            'dueDate' => $this->dueDate->toISOString(),
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'word' => [
                    'id' => $item->word->id,
                    'ru' => $item->word->ru,
                    'en' => $item->word->en,
                    'grade' => $item->word->grade,
                ],
            ])->all(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
