<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['phone', 'name', 'avatar_path'])]
#[Hidden(['pin_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * @return HasMany<AuthSession, $this>
     */
    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSession::class);
    }

    /**
     * @return HasOne<UserInfo, $this>
     */
    public function info(): HasOne
    {
        return $this->hasOne(UserInfo::class);
    }

    /**
     * @return HasMany<WordRepeat, $this>
     */
    public function wordRepeats(): HasMany
    {
        return $this->hasMany(WordRepeat::class);
    }

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /**
     * @return Attribute<int|null, never>
     */
    protected function grade(): Attribute
    {
        return Attribute::get(
            fn (): ?int => $this->info === null
                ? null
                : max(0, now()->year - $this->info->first_grade_year),
        );
    }
}
