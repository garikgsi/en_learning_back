<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminDailyExerciseRequest;
use App\Http\Resources\Api\V1\ExerciseResource;
use App\Models\User;
use App\Services\AdminDailyExerciseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDailyExerciseController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user() instanceof User && $request->user()->isAdmin(), 403);

        return response()->json([
            'items' => User::query()->with('info')->orderBy('name')->orderBy('id')->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'grade' => $user->grade,
                ])->all(),
        ]);
    }

    public function store(AdminDailyExerciseRequest $request, AdminDailyExerciseService $service): JsonResponse
    {
        $data = $request->validated();
        $result = $service->assign(
            User::query()->findOrFail($data['userId']),
            array_map(fn ($id): int => (int) $id, $data['wordIds']),
            CarbonImmutable::parse($data['dueDate']),
            (bool) $data['replaceExisting'],
        );

        return response()->json([
            'item' => ExerciseResource::make($result['exercise'])->resolve($request),
            'wasReplaced' => $result['replaced'],
        ], $result['replaced'] ? 200 : 201);
    }
}
