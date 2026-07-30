<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseCompleteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exercise_id' => $this->exercise_id,
            'exercise_items_result' => $this->itemResults->map(
                fn ($result): array => [
                    'id' => $result->id,
                    'exercise_item_id' => $result->exercise_item_id,
                    'errors_count' => $result->errors_count,
                    'hints_count' => $result->hints_count,
                    'lang_id' => $result->lang_id,
                    'variants' => $result->variants,
                    'created_at' => $result->created_at,
                    'updated_at' => $result->updated_at,
                ],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
