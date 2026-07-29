<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DictionaryIndexRequest;
use App\Http\Resources\Api\V1\WordResource;
use App\Models\User;
use App\Models\Word;
use Illuminate\Http\JsonResponse;

class DictionaryController extends Controller
{
    public function index(DictionaryIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $userInfo = $user->info;

        if ($userInfo === null) {
            return response()->json([
                'message' => 'Не указан год поступления пользователя в первый класс.',
                'code' => 'USER_INFO_REQUIRED',
            ], 409);
        }

        $validated = $request->validated();
        $search = $validated['search'] ?? '';
        $perPage = $validated['perPage'] ?? 30;
        $availableGrade = $user->grade;

        $query = Word::query()
            ->where('grade', '<=', $availableGrade)
            ->withCount([
                'repeats as repeat_count' => fn ($query) => $query
                    ->where('user_id', $user->id),
                'repeats as successful_repeat_count' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('errors_count', 0),
                'repeats as failed_repeat_count' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('errors_count', '>', 0),
            ]);

        if ($search !== '') {
            $escapedSearch = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                $search,
            );
            $pattern = "%{$escapedSearch}%";
            $likeOperator = $query->getConnection()->getDriverName() === 'pgsql'
                ? 'ILIKE'
                : 'LIKE';

            $query->where(function ($query) use ($likeOperator, $pattern): void {
                $query
                    ->whereRaw("ru {$likeOperator} ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("en {$likeOperator} ? ESCAPE '\\'", [$pattern]);
            });
        }

        $words = $query
            ->orderBy('ru')
            ->paginate($perPage);

        return response()->json([
            'items' => WordResource::collection($words->items())->resolve($request),
            'total' => $words->total(),
            'page' => $words->currentPage(),
            'perPage' => $words->perPage(),
            'lastPage' => $words->lastPage(),
            'availableGrade' => $availableGrade,
        ]);
    }
}
