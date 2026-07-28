<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PinHasher
{
    public function make(string $pin): string
    {
        return Hash::driver('argon2id')->make($this->peppered($pin));
    }

    public function check(string $pin, string $hash): bool
    {
        return Hash::driver('argon2id')->check($this->peppered($pin), $hash);
    }

    private function peppered(string $pin): string
    {
        $pepper = config('auth.pin_pepper');

        throw_unless(is_string($pepper) && $pepper !== '', RuntimeException::class);

        return hash_hmac('sha256', $pin, $pepper);
    }
}
