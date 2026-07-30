<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserWordRepetitionStoreRequest;
use App\Models\User;
use App\Models\UserWordRepetition;
use Illuminate\Http\JsonResponse;

class UserWordRepetitionController extends Controller
{
    public function __invoke(
        UserWordRepetitionStoreRequest $request,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $wordId = $request->integer('word_id');

        UserWordRepetition::query()->upsert(
            [[
                'word_id' => $wordId,
                'user_id' => $user->id,
                'is_active' => true,
            ]],
            ['user_id', 'word_id'],
            ['is_active'],
        );

        $repetition = UserWordRepetition::query()
            ->where('user_id', $user->id)
            ->where('word_id', $wordId)
            ->sole();

        return response()->json([
            'word_id' => $repetition->word_id,
            'is_active' => $repetition->is_active,
        ]);
    }
}
