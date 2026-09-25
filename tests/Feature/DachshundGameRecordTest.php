<?php

namespace Tests\Feature;

use App\Models\DachshundGameRecord;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DachshundGameRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_personal_and_global_records(): void
    {
        $user = User::factory()->create();
        $leader = User::factory()->create();
        DachshundGameRecord::query()->create([
            'user_id' => $user->id,
            'best_score' => 42,
            'games_played' => 2,
        ]);
        DachshundGameRecord::query()->create([
            'user_id' => $leader->id,
            'best_score' => 91,
            'games_played' => 1,
        ]);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dachshund-game/records')
            ->assertOk()
            ->assertJson([
                'personalBest' => 42,
                'globalBest' => 91,
            ]);
    }

    public function test_result_only_increases_best_score_and_counts_games(): void
    {
        $user = User::factory()->create();
        $token = $this->accessToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/dachshund-game/results', ['score' => 25])
            ->assertOk()
            ->assertJson([
                'personalBest' => 25,
                'globalBest' => 25,
                'gamesPlayed' => 1,
            ]);

        $this->withToken($token)
            ->postJson('/api/v1/dachshund-game/results', ['score' => 12])
            ->assertOk()
            ->assertJson([
                'personalBest' => 25,
                'globalBest' => 25,
                'gamesPlayed' => 2,
            ]);
    }

    public function test_test_users_do_not_affect_global_record(): void
    {
        $user = User::factory()->create();
        $testUser = User::factory()->create(['is_test' => true]);
        DachshundGameRecord::query()->create([
            'user_id' => $testUser->id,
            'best_score' => 999,
            'games_played' => 1,
        ]);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/dachshund-game/records')
            ->assertOk()
            ->assertJson([
                'personalBest' => 0,
                'globalBest' => 0,
            ]);
    }

    public function test_user_can_preload_versioned_letter_audio(): void
    {
        $manifest = $this
            ->getJson('/api/v1/dachshund-game/audio')
            ->assertOk()
            ->assertJsonPath('locale', 'en-GB')
            ->assertJsonCount(26, 'letters')
            ->json();

        $this->get($manifest['letters']['A'])
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
