<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExerciseCompleteRequest;
use App\Http\Requests\Api\V1\ExerciseIndexRequest;
use App\Http\Requests\Api\V1\ExerciseStatisticsRequest;
use App\Http\Resources\Api\V1\ExerciseCompleteResource;
use App\Http\Resources\Api\V1\ExerciseResource;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseCompletionService;
use App\Services\ExerciseStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function complete(
        ExerciseCompleteRequest $request,
        ExerciseCompletionService $completionService,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $exercise = Exercise::query()
            ->where('user_id', $user->id)
            ->findOrFail($validated['exercise_id']);

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
            ->orderBy('dueDate')
            ->get();

        return response()->json([
            'items' => ExerciseResource::collection($exercises)->resolve($request),
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

        return response()->json([
            'items' => $items,
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
}
