<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'client_request_id', 'coins', 'kopecks_per_coin', 'processed_at', 'processed_by'])]
class MonetizationRequest extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['coins' => 'integer', 'kopecks_per_coin' => 'integer', 'processed_at' => 'immutable_datetime'];
    }
}
