<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DachshundGameResultRequest;
use App\Models\DachshundGameRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DachshundGameController extends Controller
{
    private const LETTER_AUDIO_DIRECTORY = 'audio/dachshund/alphabet/en-gb';

    public function records(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json([
            'personalBest' => (int) (DachshundGameRecord::query()
                ->where('user_id', $user->id)
                ->value('best_score') ?? 0),
            'globalBest' => $this->globalBest(),
        ]);
    }

    public function store(DachshundGameResultRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $score = (int) $request->validated('score');
        $record = DB::transaction(function () use ($user, $score): DachshundGameRecord {
            $record = DachshundGameRecord::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                return DachshundGameRecord::query()->create([
                    'user_id' => $user->id,
                    'best_score' => $score,
                    'games_played' => 1,
                ]);
            }

            $record->games_played += 1;
            $record->best_score = max($record->best_score, $score);
            $record->save();

            return $record;
        });

        return response()->json([
            'personalBest' => $record->best_score,
            'globalBest' => $this->globalBest(),
            'gamesPlayed' => $record->games_played,
        ]);
    }

    public function audioManifest(): JsonResponse
    {
        $sourceManifestPath = public_path(self::LETTER_AUDIO_DIRECTORY.'/sources.json');
        abort_unless(is_file($sourceManifestPath), 503, 'Letter audio is not available.');

        $versionHash = hash_init('sha256');
        hash_update_file($versionHash, $sourceManifestPath);

        foreach (range('a', 'z') as $letter) {
            $audioPath = public_path(self::LETTER_AUDIO_DIRECTORY."/$letter.mp3");
            abort_unless(is_file($audioPath), 503, 'Letter audio is not available.');
            hash_update_file($versionHash, $audioPath);
        }

        $version = substr(hash_final($versionHash), 0, 16);
        $letters = [];

        foreach (range('A', 'Z') as $letter) {
            $letters[$letter] = sprintf(
                '/api/v1/dachshund-game/audio/%s?v=%s',
                strtolower($letter),
                $version,
            );
        }

        return response()->json([
            'locale' => 'en-GB',
            'version' => $version,
            'letters' => $letters,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function letterAudio(string $letter): BinaryFileResponse
    {
        $normalizedLetter = strtolower($letter);
        abort_unless(preg_match('/^[a-z]$/', $normalizedLetter) === 1, 404);

        $audioPath = public_path(self::LETTER_AUDIO_DIRECTORY."/$normalizedLetter.mp3");
        abort_unless(is_file($audioPath), 404);

        return response()->file($audioPath, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type' => 'audio/mpeg',
            'ETag' => '"'.hash_file('sha256', $audioPath).'"',
        ]);
    }

    private function globalBest(): int
    {
        return (int) (DachshundGameRecord::query()
            ->whereHas('user', fn (Builder $query): Builder => $query->where('is_test', false))
            ->max('best_score') ?? 0);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
