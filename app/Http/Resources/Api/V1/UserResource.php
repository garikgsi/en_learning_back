<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * @return array<string, int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'grade' => $this->grade,
            'avatar' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : '',
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
