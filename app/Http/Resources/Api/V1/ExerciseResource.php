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
                    'ruVariants' => $item->word->ru_variants ?? [],
                    'enVariants' => $item->word->en_variants ?? [],
                    'transcription' => $item->word->transcription,
                    'grade' => $item->word->grade,
                ],
                'plural' => $item->word->plural === null
                    ? null
                    : [
                        'id' => $item->word->plural->id,
                        'en' => $item->word->plural->plural_en,
                        'ru' => $item->word->plural->plural_ru,
                        'transcription' => $item->word->plural
                            ->plural_transcription,
                    ],
            ])->all(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
