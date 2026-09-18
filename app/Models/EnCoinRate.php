<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kopecks_per_coin', 'updated_by'])]
class EnCoinRate extends Model
{
    protected $table = 'encoin_rates';

    protected function casts(): array
    {
        return ['kopecks_per_coin' => 'integer'];
    }
}
