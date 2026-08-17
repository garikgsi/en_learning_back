<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DictionaryIndexRequest;
use App\Http\Requests\Api\V1\DictionarySyncRequest;
use App\Http\Resources\Api\V1\WordResource;
use App\Models\User;
use App\Models\Word;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
            ->withExists([
                'userRepetitions as is_active' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_active', true),
            ])
            ->withCount([
                'exerciseItemResults as repeat_count' => fn ($query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    ),
                'exerciseItemResults as successful_repeat_count' => fn ($query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    )
                    ->where('errors_count', 0),
                'exerciseItemResults as failed_repeat_count' => fn ($query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    )
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

    public function sync(DictionarySyncRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->info === null) {
            return response()->json([
                'message' => 'Не указан год поступления пользователя в первый класс.',
                'code' => 'USER_INFO_REQUIRED',
            ], 409);
        }

        $availableGrade = $user->grade;
        $latestCreatedAt = Word::query()
            ->where('grade', '<=', $availableGrade)
            ->max('created_at');
        $createdAfter = $request->validated('createdAfter');
        $cachedAvailableGrade = $request->validated('availableGrade');
        $cachedAvailableGrade = $cachedAvailableGrade === null
            ? null
            : (int) $cachedAvailableGrade;
        $isFullSync = ! is_string($createdAfter)
            || $createdAfter === ''
            || $cachedAvailableGrade !== $availableGrade;
        $query = $this->wordsForUser($user)
            ->where('grade', '<=', $availableGrade);

        if (! $isFullSync) {
            $query->where(
                'created_at',
                '>',
                CarbonImmutable::parse($createdAfter),
            );
        }

        $perPage = (int) ($request->validated('perPage') ?? 500);
        $words = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'items' => WordResource::collection(
                $words->items(),
            )->resolve($request),
            'latestCreatedAt' => $latestCreatedAt === null
                ? null
                : CarbonImmutable::parse($latestCreatedAt)->toISOString(),
            'availableGrade' => $availableGrade,
            'isFullSync' => $isFullSync,
            'page' => $words->currentPage(),
            'perPage' => $words->perPage(),
            'lastPage' => $words->lastPage(),
        ]);
    }

    /**
     * @return Builder<Word>
     */
    private function wordsForUser(User $user): Builder
    {
        return Word::query()
            ->withExists([
                'userRepetitions as is_active' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_active', true),
            ])
            ->withCount([
                'exerciseItemResults as repeat_count' => fn ($query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    ),
                'exerciseItemResults as successful_repeat_count' => fn ($query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    )
                    ->where('errors_count', 0),
                'exerciseItemResults as failed_repeat_count' => fn ($query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    )
                    ->where('errors_count', '>', 0),
            ]);
    }
}
