<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DictionaryIndexRequest;
use App\Http\Requests\Api\V1\DictionaryLookupRequest;
use App\Http\Requests\Api\V1\DictionaryStoreRequest;
use App\Http\Requests\Api\V1\DictionarySyncRequest;
use App\Http\Resources\Api\V1\WordResource;
use App\Models\User;
use App\Models\Word;
use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\DictionaryLookupService;
use App\Services\Dictionary\DictionarySpeechService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DictionaryController extends Controller
{
    public function audio(
        Word $word,
        DictionarySpeechService $speechService,
        PhoneticsDriver $phoneticsDriver,
    ): StreamedResponse|JsonResponse {
        if ($word->transcription === null) {
            $transcription = $phoneticsDriver
                ->find($word->en)?->transcription;

            if ($transcription !== null) {
                $word->update(['transcription' => $transcription]);
            }
        }

        $audio = $speechService->audio(new SpeechRequest($word->en));

        if ($audio === null) {
            return response()->json([
                'message' => 'Для слова пока нет доступного произношения.',
                'code' => 'WORD_AUDIO_UNAVAILABLE',
            ], 404);
        }

        return Storage::disk((string) config(
            'dictionary.audio.disk',
            'dictionary_audio',
        ))->response($audio->path, null, [
            'Content-Type' => $audio->contentType,
            'Cache-Control' => 'public, max-age=86400',
        ], 'inline');
    }

    public function lookup(
        DictionaryLookupRequest $request,
        DictionaryLookupService $lookupService,
    ): JsonResponse {
        try {
            $lookup = $lookupService->lookup(
                $request->validated('word'),
                $request->validated('sourceLanguage'),
            );
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не удалось получить перевод из внешнего словаря.',
                'code' => 'DICTIONARY_LOOKUP_UNAVAILABLE',
            ], 503);
        }

        $existingWords = Word::query()
            ->where(function (Builder $query) use ($lookup): void {
                $query
                    ->whereRaw('LOWER(ru) = ?', [mb_strtolower($lookup['russian'])])
                    ->orWhereRaw('LOWER(en) = ?', [mb_strtolower($lookup['english'])]);
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        return response()->json([
            ...$lookup,
            'existingWords' => WordResource::collection(
                $existingWords,
            )->resolve($request),
        ]);
    }

    public function store(DictionaryStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->info === null) {
            return response()->json([
                'message' => 'Не указан год поступления пользователя в первый класс.',
                'code' => 'USER_INFO_REQUIRED',
            ], 409);
        }

        $validated = $request->validated();
        $existingWord = Word::query()
            ->whereRaw('LOWER(ru) = ?', [mb_strtolower($validated['russian'])])
            ->whereRaw('LOWER(en) = ?', [mb_strtolower($validated['english'])])
            ->first();

        if ($existingWord !== null) {
            return response()->json([
                'item' => WordResource::make($existingWord)->resolve($request),
                'wasCreated' => false,
            ]);
        }

        $word = Word::query()->create([
            'ru' => $validated['russian'],
            'en' => $validated['english'],
            'transcription' => $validated['transcription'] ?? null,
            'ru_variants' => [],
            'en_variants' => [],
            'grade' => max(1, $user->grade),
        ]);

        return response()->json([
            'item' => WordResource::make($word)->resolve($request),
            'wasCreated' => true,
        ], 201);
    }

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
        $revisionReleasedAt = CarbonImmutable::parse(
            config('dictionary.revision_released_at'),
        );

        if ($latestCreatedAt === null
            || CarbonImmutable::parse($latestCreatedAt)->lt($revisionReleasedAt)) {
            $latestCreatedAt = $revisionReleasedAt;
        }

        $createdAfter = $request->validated('createdAfter');
        $cachedAvailableGrade = $request->validated('availableGrade');
        $cachedAvailableGrade = $cachedAvailableGrade === null
            ? null
            : (int) $cachedAvailableGrade;
        $revision = (int) config('dictionary.revision');
        $cachedRevision = $request->validated('revision');
        $cachedRevision = $cachedRevision === null
            ? null
            : (int) $cachedRevision;
        $hasOutdatedLegacyCache = $cachedRevision === null
            && is_string($createdAfter)
            && $createdAfter !== ''
            && CarbonImmutable::parse($createdAfter)->lt($revisionReleasedAt);
        $isFullSync = ! is_string($createdAfter)
            || $createdAfter === ''
            || $cachedAvailableGrade !== $availableGrade
            || ($cachedRevision !== null && $cachedRevision !== $revision)
            || $hasOutdatedLegacyCache;
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
            'latestCreatedAt' => CarbonImmutable::parse(
                $latestCreatedAt,
            )->toISOString(),
            'availableGrade' => $availableGrade,
            'revision' => $revision,
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
