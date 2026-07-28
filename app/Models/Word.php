<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ru', 'en', 'grade'])]
class Word extends Model
{
    /**
     * @return HasMany<WordRepeat, $this>
     */
    public function repeats(): HasMany
    {
        return $this->hasMany(WordRepeat::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
        ];
    }
}
