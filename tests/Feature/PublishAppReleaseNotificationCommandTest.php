<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishAppReleaseNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_one_notification_per_user_and_version(): void
    {
        $users = User::factory()->count(2)->create();

        $this->artisan('notifications:publish-release', [
            'version' => '0.1.0-rc.15',
        ])->expectsOutput('Release notifications created: 2.')
            ->assertSuccessful();
        $this->artisan('notifications:publish-release', [
            'version' => '0.1.0-rc.15',
        ])->expectsOutput('Release notifications created: 0.')
            ->assertSuccessful();

        $this->assertDatabaseCount('user_notifications', 2);

        foreach ($users as $user) {
            $this->assertDatabaseHas('user_notifications', [
                'user_id' => $user->id,
                'type' => 'app.release.available',
                'deduplication_key' => "app-release:0.1.0-rc.15:user:{$user->id}",
            ]);
        }
    }
}
