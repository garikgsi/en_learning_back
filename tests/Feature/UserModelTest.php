<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_have_the_user_role_by_default(): void
    {
        $user = User::factory()->create(['phone' => '+79262260386']);

        $this->assertSame(UserRole::user, $user->role);
        $this->assertSame(UserRole::user, $user->refresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_admin_role_is_cast_and_can_be_checked(): void
    {
        $user = User::factory()->create(['role' => UserRole::admin]);

        $this->assertSame(UserRole::admin, $user->refresh()->role);
        $this->assertTrue($user->isAdmin());
    }

    public function test_database_defaults_to_the_user_role_without_the_model_default(): void
    {
        $user = User::factory()->make();
        $attributes = $user->getAttributes();
        unset($attributes['role']);
        $user->setRawAttributes($attributes);
        $user->save();

        $this->assertSame(UserRole::user, $user->refresh()->role);
    }

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
