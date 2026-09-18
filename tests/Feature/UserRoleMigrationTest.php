<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_assigns_only_the_two_existing_administrators(): void
    {
        $firstAdmin = User::factory()->create(['phone' => '+79262260386']);
        $secondAdmin = User::factory()->create(['phone' => '+79031611479']);
        $regularUser = User::factory()->create(['phone' => '+79917036701']);
        $sameFirstSuffix = User::factory()->create(['phone' => '+79990000386']);
        $sameSecondSuffix = User::factory()->create(['phone' => '+79990001479']);

        $migration = require database_path('migrations/2026_09_18_000026_add_role_to_users_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'role'));

        $migration->up();

        $this->assertSame(UserRole::admin, $firstAdmin->refresh()->role);
        $this->assertSame(UserRole::admin, $secondAdmin->refresh()->role);
        foreach ([$regularUser, $sameFirstSuffix, $sameSecondSuffix] as $user) {
            $this->assertSame(UserRole::user, $user->refresh()->role);
        }

        $this->assertDatabaseCount('users', 5);
    }
}
