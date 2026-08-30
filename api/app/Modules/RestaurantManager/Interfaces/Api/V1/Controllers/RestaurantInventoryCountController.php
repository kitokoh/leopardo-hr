<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantInventoryCountService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantInventoryCountRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantInventoryCountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * RESTO-504 (#6203) — Inventaires physiques (comptage, écarts, approbation).
 */
class RestaurantInventoryCountController extends Controller
{
    public function __construct(
        private readonly RestaurantInventoryCountService $inventoryCounts,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantInventoryCount::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $counts = RestaurantInventoryCount::query()
            ->with('items')
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', (string) $v))
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantInventoryCountResource::collection($counts)->response();
    }

    public function store(StoreRestaurantInventoryCountRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantInventoryCount::class)) {
            abort(403);
        }

        $count = $this->inventoryCounts->createWithExpected($actor, (int) $request->validated('branch_id'));

        return (new RestaurantInventoryCountResource($count))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantInventoryCount $restaurantInventoryCount): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantInventoryCount->company_id) {
            abort(404);
        }

        return (new RestaurantInventoryCountResource($restaurantInventoryCount->load('items')))->response();
    }

    public function recordItem(Request $request, RestaurantInventoryCount $restaurantInventoryCount, RestaurantInventoryCountItem $item): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantInventoryCount->company_id || $item->count_id !== $restaurantInventoryCount->id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantInventoryCount)) {
            abort(403);
        }

        if ($restaurantInventoryCount->status->value !== 'draft') {
            return response()->json(['message' => 'Un inventaire soumis ou approuvé est immutable.'], 422);
        }

        $request->validate([
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'reason_code' => ['nullable', 'string', 'max:30'],
        ]);

        $item = $this->inventoryCounts->recordCounted(
            $item,
            (string) $request->input('counted_qty'),
            $request->input('reason_code'),
        );

        return (new RestaurantInventoryCountResource($restaurantInventoryCount->load('items')))->response();
    }

    public function submit(Request $request, RestaurantInventoryCount $restaurantInventoryCount): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantInventoryCount->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantInventoryCount)) {
            abort(403);
        }

        try {
            $count = $this->inventoryCounts->submit($restaurantInventoryCount, $actor);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantInventoryCountResource($count))->response();
    }

    public function approve(Request $request, RestaurantInventoryCount $restaurantInventoryCount): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantInventoryCount->company_id) {
            abort(404);
        }

        if ($actor->cannot('approve', $restaurantInventoryCount)) {
            abort(403);
        }

        try {
            $count = $this->inventoryCounts->approve($restaurantInventoryCount, $actor);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantInventoryCountResource($count))->response();
    }
}
