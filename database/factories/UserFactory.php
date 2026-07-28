<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\Auth\PinHasher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $pinHash;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+7'.fake()->unique()->numerify('##########'),
            'pin_hash' => static::$pinHash ??= app(PinHasher::class)->make('1234'),
            'avatar_path' => null,
        ];
    }
}
