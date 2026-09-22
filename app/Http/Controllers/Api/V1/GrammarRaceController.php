<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GrammarRaceGameCode;
use App\Exceptions\GrammarRaceException;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GrammarRaceAbandonRequest;
use App\Http\Requests\Api\V1\GrammarRaceCompleteRequest;
use App\Http\Requests\Api\V1\GrammarRaceStartRequest;
use App\Http\Resources\Api\V1\GrammarRaceSessionResource;
use App\Models\GrammarRaceSession;
use App\Models\User;
use App\Services\GrammarRace\GrammarRaceSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrammarRaceController extends Controller
{
    public function status(
        Request $request,
        string $gameCode,
        GrammarRaceSessionService $service,
    ): JsonResponse {
        $status = $service->status($this->authenticatedUser($request), $this->gameCode($gameCode));
        $active = $status['activeSession'];
        $status['activeSession'] = $active === null
            ? null
            : (new GrammarRaceSessionResource($active))->resolve($request);

        return response()->json($status);
    }

    public function start(
        GrammarRaceStartRequest $request,
        string $gameCode,
        GrammarRaceSessionService $service,
    ): JsonResponse {
        try {
            $result = $service->start(
                $this->authenticatedUser($request),
                $this->gameCode($gameCode),
                $request->validated('clientRequestId'),
            );
        } catch (IdempotencyKeyReusedException) {
            return $this->idempotencyError();
        } catch (GrammarRaceException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'item' => (new GrammarRaceSessionResource($result['session']))->resolve($request),
        ], $result['created'] ? 201 : 200);
    }

    public function show(Request $request, string $session): JsonResponse
    {
        $item = GrammarRaceSession::query()
            ->where('user_id', $this->authenticatedUser($request)->id)
            ->with(['tasks', 'rounds'])
            ->findOrFail($session);

        return response()->json([
            'item' => (new GrammarRaceSessionResource($item))->resolve($request),
        ]);
    }

    public function complete(
        GrammarRaceCompleteRequest $request,
        string $session,
        GrammarRaceSessionService $service,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        $item = GrammarRaceSession::query()->where('user_id', $user->id)->findOrFail($session);
        $validated = $request->validated();

        try {
            $result = $service->complete(
                $user,
                $item,
                $validated['clientResultId'],
                CarbonImmutable::parse($validated['completedAt']),
                $validated['rounds'],
            );
        } catch (IdempotencyKeyReusedException) {
            return $this->idempotencyError();
        } catch (GrammarRaceException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'item' => (new GrammarRaceSessionResource($result['session']))->resolve($request),
        ], $result['created'] ? 201 : 200);
    }

    public function abandon(
        GrammarRaceAbandonRequest $request,
        string $session,
        GrammarRaceSessionService $service,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        $item = GrammarRaceSession::query()->where('user_id', $user->id)->findOrFail($session);
        $validated = $request->validated();

        try {
            $result = $service->abandon(
                $user,
                $item,
                $validated['clientResultId'],
                CarbonImmutable::parse($validated['abandonedAt']),
            );
        } catch (IdempotencyKeyReusedException) {
            return $this->idempotencyError();
        } catch (GrammarRaceException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'item' => (new GrammarRaceSessionResource($result['session']))->resolve($request),
        ], $result['created'] ? 201 : 200);
    }

    private function gameCode(string $value): GrammarRaceGameCode
    {
        return GrammarRaceGameCode::tryFrom($value) ?? abort(404);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function idempotencyError(): JsonResponse
    {
        return response()->json([
            'message' => 'Идентификатор запроса уже использован для другого результата.',
            'code' => 'IDEMPOTENCY_KEY_REUSED',
        ], 409);
    }

    private function domainError(GrammarRaceException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->errorCode,
        ], $exception->status);
    }
}
