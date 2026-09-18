<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MonetizationRequestResource;
use App\Models\EnCoinRate;
use App\Models\MonetizationRequest;
use App\Models\User;
use App\Services\EnCoinService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonetizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'processed', 'all'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $status = $data['status'] ?? 'pending';
        $items = MonetizationRequest::query()
            ->when($status === 'pending', fn ($q) => $q->whereNull('processed_at'))
            ->when($status === 'processed', fn ($q) => $q->whereNotNull('processed_at'))
            ->with(['user' => fn ($q) => $this->withBalance($q->getQuery())])
            ->latest('id')->paginate(30);

        return response()->json([
            'items' => MonetizationRequestResource::collection($items->items())->resolve($request),
            'page' => $items->currentPage(), 'lastPage' => $items->lastPage(), 'total' => $items->total(),
        ]);
    }

    public function process(Request $request, MonetizationRequest $monetizationRequest, EnCoinService $service): JsonResponse
    {
        $service->process($monetizationRequest, $this->admin($request));
        $item = MonetizationRequest::query()->with(['user' => fn ($q) => $this->withBalance($q->getQuery())])
            ->findOrFail($monetizationRequest->id);

        return response()->json(['item' => MonetizationRequestResource::make($item)->resolve($request)]);
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $user;
    }

    public function rate(Request $request, EnCoinService $service): JsonResponse
    {
        $this->admin($request);

        return response()->json(['rublesPerCoin' => $service->rate()->kopecks_per_coin / 100]);
    }

    public function updateRate(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate([
            'rublesPerCoin' => ['required', 'numeric', 'min:0.01', 'max:100000', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        ]);
        $parts = explode('.', (string) $data['rublesPerCoin']);
        $kopecks = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        EnCoinRate::query()->findOrFail(1)->update(['kopecks_per_coin' => $kopecks, 'updated_by' => $admin->id]);

        return response()->json(['rublesPerCoin' => $kopecks / 100]);
    }

    private function withBalance(Builder $query): Builder
    {
        return $query->withSum('enCoinEntries as enc_balance', 'amount')
            ->withSum(['monetizationRequests as enc_reserved' => fn ($q) => $q->whereNull('processed_at')], 'coins');
    }
}
