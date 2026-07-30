<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWordRepetition;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWordRepetitionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_any_word_and_reactivate_it(): void
    {
        $user = User::factory()->create();
        $word = Word::query()->create([
            'ru' => 'фраза для повторения',
            'en' => 'phrase to repeat',
            'grade' => 99,
        ]);

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/repetition-list/words', [
                'word_id' => $word->id,
            ])
            ->assertOk()
            ->assertJsonPath('word_id', $word->id)
            ->assertJsonPath('is_active', true);

        UserWordRepetition::query()->update(['is_active' => false]);

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/repetition-list/words', [
                'word_id' => $word->id,
            ])
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseCount('user_word_repetition', 1);
        $this->assertDatabaseHas('user_word_repetition', [
            'user_id' => $user->id,
            'word_id' => $word->id,
            'is_active' => true,
        ]);
    }

    public function test_repetition_word_is_required_and_must_exist(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/repetition-list/words', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('word_id');

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/repetition-list/words', [
                'word_id' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('word_id');
    }

    public function test_repetition_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/repetition-list/words', [
            'word_id' => 1,
        ])->assertUnauthorized();
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
