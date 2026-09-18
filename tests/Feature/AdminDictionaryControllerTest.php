<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserWordRepetition;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDictionaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_every_grade_in_index_and_sync_without_user_info(): void
    {
        $admin = $this->admin();
        $this->word('дом', 'home', 1);
        $this->word('университет', 'university', 99);

        $this->withToken($this->token($admin))->getJson('/api/v1/dictionary')
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('availableGrade', null);
        $this->getJson('/api/v1/dictionary/sync')
            ->assertOk()->assertJsonCount(2, 'items')->assertJsonPath('availableGrade', null);
    }

    public function test_admin_also_ignores_the_grade_in_existing_user_info(): void
    {
        $admin = $this->admin();
        $admin->info()->create(['first_grade_year' => now()->year - 1]);
        $word = $this->word('университет', 'university', 99);

        $this->withToken($this->token($admin))->getJson('/api/v1/dictionary?search=university')
            ->assertOk()->assertJsonPath('items.0.id', $word->id);
    }

    public function test_changing_between_admin_and_user_scope_forces_a_full_sync(): void
    {
        $admin = $this->admin();
        $admin->info()->create(['first_grade_year' => now()->year - 2]);
        $this->word('дом', 'home', 1);
        $this->word('университет', 'university', 99);
        $token = $this->token($admin);

        $response = $this->withToken($token)->getJson('/api/v1/dictionary/sync');
        $query = http_build_query([
            'createdAfter' => $response->json('latestCreatedAt'),
            'updatedAfter' => $response->json('latestUpdatedAt'),
            'availableGrade' => 2,
            'revision' => $response->json('revision'),
        ]);
        $this->getJson('/api/v1/dictionary/sync?'.$query)
            ->assertOk()->assertJsonPath('isFullSync', true)->assertJsonCount(2, 'items');

        $admin->role = UserRole::user;
        $admin->save();
        $this->getJson('/api/v1/dictionary/sync?'.http_build_query([
            'createdAfter' => $response->json('latestCreatedAt'),
            'updatedAfter' => $response->json('latestUpdatedAt'),
            'revision' => $response->json('revision'),
        ]))->assertOk()->assertJsonPath('isFullSync', true)->assertJsonCount(1, 'items');
    }

    public function test_only_admins_can_open_and_update_a_word(): void
    {
        $word = $this->word('дом', 'home');
        $url = '/api/v1/dictionary/words/'.$word->id;
        $this->getJson($url)->assertUnauthorized();
        $this->patchJson($url, $this->payload())->assertUnauthorized();

        $this->withToken($this->token(User::factory()->create()))->getJson($url)->assertForbidden();
        $this->patchJson($url, $this->payload())->assertForbidden();
        $this->assertSame('home', $word->refresh()->en);

        $this->withToken($this->token($this->admin()))->getJson($url)
            ->assertOk()->assertJsonPath('item.id', $word->id);
    }

    public function test_admin_updates_values_and_variants_without_changing_grade_or_repetitions(): void
    {
        $admin = $this->admin();
        $word = $this->word('дом', 'home', 99);
        UserWordRepetition::query()->create(['user_id' => $admin->id, 'word_id' => $word->id, 'is_active' => true]);

        $this->withToken($this->token($admin))->patchJson('/api/v1/dictionary/words/'.$word->id, [
            ...$this->payload(), 'russian' => '  жилище  ', 'englishVariants' => [' house '], 'grade' => 1,
        ])->assertOk()->assertJsonPath('item.ru', 'жилище')
            ->assertJsonPath('item.enVariants', ['house'])->assertJsonPath('item.ruVariants', ['здание'])
            ->assertJsonPath('item.grade', 99)->assertJsonPath('item.is_active', true);
        $this->assertDatabaseCount('words', 1);
        $this->assertDatabaseCount('user_word_repetition', 1);
    }

    public function test_admin_can_clear_variants_and_preserve_legacy_punctuation(): void
    {
        $word = $this->word('Спасибо, хорошо', "I'm fine, thanks");
        $word->update(['ru_variants' => ['хорошо'], 'en_variants' => ['fine']]);
        $this->withToken($this->token($this->admin()))->patchJson('/api/v1/dictionary/words/'.$word->id, [
            'russian' => $word->ru, 'english' => $word->en, 'russianVariants' => [], 'englishVariants' => [],
        ])->assertOk()->assertJsonPath('item.en', "I'm fine, thanks")
            ->assertJsonPath('item.enVariants', [])->assertJsonPath('item.ruVariants', []);
    }

    public function test_invalid_values_and_duplicate_variants_do_not_change_the_word(): void
    {
        $word = $this->word('дом', 'home');
        $this->withToken($this->token($this->admin()))->patchJson('/api/v1/dictionary/words/'.$word->id, [
            ...$this->payload(), 'english' => '   ', 'russianVariants' => [''], 'englishVariants' => ['house', ' HOUSE '],
        ])->assertUnprocessable()->assertJsonValidationErrors(['english', 'russianVariants.0', 'englishVariants.0']);
        $this->assertSame('home', $word->refresh()->en);
    }

    public function test_changing_english_clears_the_old_transcription(): void
    {
        $word = $this->word('дом', 'home');
        $word->update(['transcription' => '/həʊm/']);
        $this->withToken($this->token($this->admin()))->patchJson('/api/v1/dictionary/words/'.$word->id, [
            ...$this->payload(), 'english' => 'house',
        ])->assertOk()->assertJsonPath('item.transcription', null);
    }

    public function test_admin_cannot_edit_transcription_and_value_edits_preserve_it(): void
    {
        $word = $this->word('дом', 'home');
        $word->update(['transcription' => '/həʊm/']);
        $url = '/api/v1/dictionary/words/'.$word->id;
        $this->withToken($this->token($this->admin()));
        foreach (['/haʊs/', null] as $transcription) {
            $this->patchJson($url, [...$this->payload(), 'transcription' => $transcription])
                ->assertUnprocessable()->assertJsonValidationErrors('transcription');
            $this->assertSame('/həʊm/', $word->refresh()->transcription);
        }
        $this->patchJson($url, $this->payload())->assertOk()
            ->assertJsonPath('item.transcription', '/həʊm/');
    }

    public function test_sync_includes_edits_to_old_words_at_the_exact_update_boundary(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = $this->admin();
        $word = $this->word('дом', 'home');
        $first = $this->withToken($this->token($admin))->getJson('/api/v1/dictionary/sync')->assertOk();
        $this->patchJson('/api/v1/dictionary/words/'.$word->id, $this->payload())->assertOk();

        $this->getJson('/api/v1/dictionary/sync?'.http_build_query([
            'createdAfter' => $first->json('latestCreatedAt'),
            'updatedAfter' => $first->json('latestUpdatedAt'),
            'revision' => $first->json('revision'),
        ]))->assertOk()->assertJsonPath('isFullSync', true)
            ->assertJsonPath('items.0.enVariants', ['house']);

        $regular = User::factory()->create();
        $regular->info()->create(['first_grade_year' => now()->year - 2]);
        $this->withToken($this->token($regular))->getJson('/api/v1/dictionary/sync?'.http_build_query([
            'createdAfter' => $first->json('latestCreatedAt'),
            'updatedAfter' => $first->json('latestUpdatedAt'),
            'revision' => $first->json('revision'),
            'availableGrade' => 2,
        ]))->assertOk()->assertJsonPath('items.0.enVariants', ['house']);
    }

    public function test_editing_grade_five_updates_every_affected_users_version_only(): void
    {
        $this->travelTo(now()->startOfSecond());
        $word = $this->word('дом', 'home', 5);
        $admin = $this->admin();
        $users = [];
        foreach ([4, 5, 5, 6] as $grade) {
            $user = User::factory()->create();
            $user->info()->create(['first_grade_year' => now()->year - $grade]);
            $users[] = ['user' => $user, 'grade' => $grade];
        }
        $snapshots = [];
        foreach ($users as $index => $entry) {
            $snapshots[$index] = $this->withToken($this->token($entry['user']))
                ->getJson('/api/v1/dictionary/sync')->assertOk()->json();
        }

        $this->withToken($this->token($admin))
            ->patchJson('/api/v1/dictionary/words/'.$word->id, $this->payload())->assertOk();

        foreach ($users as $index => $entry) {
            $cached = $snapshots[$index];
            // An older client sends no updatedAfter; revision alone must refresh it.
            $response = $this->withToken($this->token($entry['user']))
                ->getJson('/api/v1/dictionary/sync?'.http_build_query([
                    'createdAfter' => $cached['latestCreatedAt'],
                    'availableGrade' => $cached['availableGrade'],
                    'revision' => $cached['revision'],
                ]))->assertOk();
            if ($entry['grade'] >= 5) {
                $response->assertJsonPath('revision', $cached['revision'] + 1)
                    ->assertJsonPath('isFullSync', true)
                    ->assertJsonPath('items.0.enVariants', ['house']);
            } else {
                $response->assertJsonPath('revision', $cached['revision'])
                    ->assertJsonPath('isFullSync', false)
                    ->assertJsonCount(0, 'items');
            }
        }

        $this->withToken($this->token($admin))->getJson('/api/v1/dictionary/sync')
            ->assertOk()->assertJsonPath('revision', 8);
    }

    public function test_multiple_edits_in_the_same_second_have_different_versions(): void
    {
        $this->travelTo(now()->startOfSecond());
        $word = $this->word('дом', 'home', 5);
        $this->withToken($this->token($this->admin()));
        $url = '/api/v1/dictionary/words/'.$word->id;
        $this->patchJson($url, $this->payload())->assertOk();
        $first = $this->getJson('/api/v1/dictionary/sync')->assertOk()->json('revision');
        $this->patchJson($url, [...$this->payload(), 'englishVariants' => ['house', 'dwelling']])->assertOk();
        $this->getJson('/api/v1/dictionary/sync')->assertOk()->assertJsonPath('revision', $first + 1);

        // Saving identical values must not trigger another refresh for all users.
        $this->patchJson($url, [...$this->payload(), 'englishVariants' => ['house', 'dwelling']])->assertOk();
        $this->getJson('/api/v1/dictionary/sync')->assertOk()->assertJsonPath('revision', $first + 1);
    }

    public function test_changing_a_words_grade_refreshes_both_scopes_and_removes_inaccessible_words(): void
    {
        $word = $this->word('дом', 'home', 5);
        $fifthGrade = User::factory()->create();
        $fifthGrade->info()->create(['first_grade_year' => now()->year - 5]);
        $sixthGrade = User::factory()->create();
        $sixthGrade->info()->create(['first_grade_year' => now()->year - 6]);
        $snapshots = [];
        foreach ([$fifthGrade, $sixthGrade] as $user) {
            $snapshots[$user->id] = $this->withToken($this->token($user))
                ->getJson('/api/v1/dictionary/sync')->assertOk()->json();
        }

        $word->update(['grade' => 6]);
        foreach ([$fifthGrade, $sixthGrade] as $user) {
            $cached = $snapshots[$user->id];
            $response = $this->withToken($this->token($user))
                ->getJson('/api/v1/dictionary/sync?'.http_build_query([
                    'createdAfter' => $cached['latestCreatedAt'],
                    'availableGrade' => $cached['availableGrade'],
                    'revision' => $cached['revision'],
                ]))->assertOk()->assertJsonPath('isFullSync', true);
            $this->assertGreaterThan($cached['revision'], $response->json('revision'));
            if ($user->is($fifthGrade)) {
                $response->assertJsonCount(0, 'items');
            } else {
                $response->assertJsonPath('items.0.id', $word->id)->assertJsonPath('items.0.grade', 6);
            }
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::admin]);
    }

    private function token(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }

    private function word(string $ru, string $en, int $grade = 1): Word
    {
        return Word::query()->create(['ru' => $ru, 'en' => $en, 'grade' => $grade]);
    }

    private function payload(): array
    {
        return ['russian' => 'дом', 'english' => 'home', 'russianVariants' => ['здание'], 'englishVariants' => ['house']];
    }
}
