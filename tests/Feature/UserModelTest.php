<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_uses_uuid_v7_and_hides_pin_hash(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUuid($user->id, 7));
        $this->assertSame('string', $user->getKeyType());
        $this->assertFalse($user->getIncrementing());
        $this->assertArrayNotHasKey('pin_hash', $user->toArray());
    }

    public function test_phone_must_be_unique(): void
    {
        $phone = '+79991234567';

        User::factory()->create(['phone' => $phone]);

        $this->expectException(QueryException::class);

        User::factory()->create(['phone' => $phone]);
    }
}
