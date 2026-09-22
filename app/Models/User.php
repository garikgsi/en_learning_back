<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read int|null $grade
 * @property bool $is_test
 * @property UserRole $role
 * @property-read int|string|null $enc_balance
 * @property-read int|string|null $enc_reserved
 * @property-read int|string|null $total_earned_coins
 * @property-read string $avatar
 */
#[Fillable(['phone', 'name', 'avatar_path'])]
#[Hidden(['pin_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $attributes = [
        'role' => 'user',
    ];

    public function isAdmin(): bool
    {
        return $this->role === UserRole::admin;
    }

    #[Scope]
    protected function nonTest(Builder $query): void
    {
        $query->where('is_test', false);
    }

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
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /** @return HasMany<EnCoinEntry, $this> */
    public function enCoinEntries(): HasMany
    {
        return $this->hasMany(EnCoinEntry::class);
    }

    /** @return HasMany<MonetizationRequest, $this> */
    public function monetizationRequests(): HasMany
    {
        return $this->hasMany(MonetizationRequest::class);
    }

    /** @return HasMany<GrammarRaceSession, $this> */
    public function grammarRaceSessions(): HasMany
    {
        return $this->hasMany(GrammarRaceSession::class);
    }

    /** @return HasMany<GrammarRaceProfile, $this> */
    public function grammarRaceProfiles(): HasMany
    {
        return $this->hasMany(GrammarRaceProfile::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * @return HasMany<UserWordRepetition, $this>
     */
    public function wordRepetitions(): HasMany
    {
        return $this->hasMany(UserWordRepetition::class);
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

    /**
     * @return Attribute<string, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : '',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'role' => UserRole::class,
        ];
    }
}
