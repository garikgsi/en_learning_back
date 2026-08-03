<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExerciseTypeCode;
use App\Exceptions\NoWordsAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExerciseCompleteRequest;
use App\Http\Requests\Api\V1\ExerciseIndexRequest;
use App\Http\Requests\Api\V1\ExerciseStatisticsRequest;
use App\Http\Resources\Api\V1\ExerciseCompleteResource;
use App\Http\Resources\Api\V1\ExerciseResource;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseCompletionService;
use App\Services\ExerciseService;
use App\Services\ExerciseStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function store(
        Request $request,
        ExerciseService $exerciseService,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);

        if ($user->grade === null) {
            return response()->json([
                'message' => 'Не указан год поступления пользователя в первый класс.',
                'code' => 'USER_INFO_REQUIRED',
            ], 409);
        }

        try {
            $exercise = $exerciseService->create(
                ExerciseTypeCode::user,
                $user,
                today(),
            );
        } catch (NoWordsAvailableException) {
            return response()->json([
                'message' => 'Нет слов в словаре',
                'code' => 'DICTIONARY_EMPTY',
            ], 409);
        }

        return response()->json([
            'item' => (new ExerciseResource($exercise))->resolve($request),
        ], 201);
    }

    public function complete(
        ExerciseCompleteRequest $request,
        ExerciseCompletionService $completionService,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $exercise = Exercise::query()
            ->where('user_id', $user->id)
            ->findOrFail($validated['exercise_id']);

        if ($this->isCompletedUserExercise($exercise)) {
            return $this->userExerciseAlreadyCompletedResponse();
        }

        $complete = $completionService->complete(
            $exercise,
            $validated['exercise_items_result'],
        );

        return (new ExerciseCompleteResource($complete))
            ->response()
            ->setStatusCode(201);
    }

    public function index(ExerciseIndexRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $exercises = $this->queryFor($user)
            ->whereBetween('dueDate', [
                CarbonImmutable::parse($validated['dateFrom']),
                CarbonImmutable::parse($validated['dateTo']),
            ])
            ->orderBy('dueDate')
            ->get();

        return response()->json([
            'items' => ExerciseResource::collection($exercises)->resolve($request),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $exercises = $this->queryFor($user)
            ->whereBetween('dueDate', [
                today(),
                today()->endOfDay(),
            ])
            ->whereDoesntHave('completions')
            ->orderBy('dueDate')
            ->get();

        return response()->json([
            'items' => ExerciseResource::collection($exercises)->resolve($request),
        ]);
    }

    public function show(Request $request, int $exercise): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $selectedExercise = $this->queryFor($user)
            ->findOrFail($exercise);

        if ($this->isCompletedUserExercise($selectedExercise)) {
            return $this->userExerciseAlreadyCompletedResponse();
        }

        return response()->json([
            'item' => (new ExerciseResource($selectedExercise))
                ->resolve($request),
        ]);
    }

    public function statistics(
        ExerciseStatisticsRequest $request,
        ExerciseStatisticsService $statisticsService,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $items = $statisticsService->forPeriod(
            $user,
            CarbonImmutable::parse($validated['dateFrom']),
            CarbonImmutable::parse($validated['dateTo']),
        );
        $now = CarbonImmutable::now();

        return response()->json([
            'items' => $items,
            'charts' => $statisticsService->charts($now),
            'attentionWords' => $statisticsService->attentionWords(
                $user,
                $now,
            ),
        ]);
    }

    /**
     * @return Builder<Exercise>
     */
    private function queryFor(User $user): Builder
    {
        return Exercise::query()
            ->where('user_id', $user->id)
            ->with(['type', 'items.word']);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function isCompletedUserExercise(Exercise $exercise): bool
    {
        return (int) $exercise->type_id === ExerciseTypeCode::user->value
            && $exercise->completions()->exists();
    }

    private function userExerciseAlreadyCompletedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Пользовательское упражнение уже выполнено и недоступно для повторения.',
            'code' => 'USER_EXERCISE_ALREADY_COMPLETED',
        ], 409);
    }
}
