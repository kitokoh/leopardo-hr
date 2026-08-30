<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\InventoryCountAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantInventoryCountRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantInventoryCountItemRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantInventoryCountItemResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantInventoryCountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-504 (#6203) — Inventaires physiques.
 *
 * `POST /inventory-counts` : crée un comptage `draft` avec les lignes
 * attendues pré-remplies depuis les niveaux de stock de la branche.
 * `PUT /inventory-counts/{count}/items/{item}` : saisie du compté (+ motif
 * si écart). `POST /inventory-counts/{count}/submit` puis `/approve`
 * (réservé manage — écart non justifié → 422 ; approbation → ajustements
 * stock). 404 sûr cross-tenant.
 */
class RestaurantInventoryCountController extends Controller
{
    public function __construct(private readonly InventoryCountAction $action)
    {
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
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->orderByDesc('counted_at')
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

        $data = $request->validated();

        $count = DB::transaction(function () use ($actor, $data): RestaurantInventoryCount {
            $count = RestaurantInventoryCount::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $data['branch_id'],
                'counted_at' => $data['counted_at'] ?? now(),
                'status' => 'draft',
                'counted_by_user_id' => $actor->id,
            ]);

            // Lignes attendues pré-remplies depuis les niveaux de stock.
            $levels = RestaurantStockLevel::query()
                ->where('company_id', $actor->company_id)
                ->where('branch_id', $data['branch_id'])
                ->get(['ingredient_id', 'quantity']);

            foreach ($levels as $level) {
                RestaurantInventoryCountItem::query()->create([
                    'company_id' => $actor->company_id,
                    'count_id' => $count->id,
                    'ingredient_id' => $level->ingredient_id,
                    'expected_qty' => $level->quantity,
                    'counted_qty' => null,
                    'variance_qty' => null,
                ]);
            }

            return $count;
        });

        return (new RestaurantInventoryCountResource($count->load('items')))->response()->setStatusCode(201);
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

    public function updateItem(UpdateRestaurantInventoryCountItemRequest $request, RestaurantInventoryCount $restaurantInventoryCount, RestaurantInventoryCountItem $restaurantInventoryCountItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantInventoryCount->company_id || $actor->company_id !== $restaurantInventoryCountItem->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantInventoryCount)) {
            abort(403);
        }

        if ($restaurantInventoryCount->status->value !== 'draft') {
            abort(409, 'Only a draft inventory count can be edited.');
        }

        if ($restaurantInventoryCountItem->count_id !== $restaurantInventoryCount->id) {
            abort(422, 'Item does not belong to this inventory count.');
        }

        $countedQty = (float) $request->input('counted_qty');
        $expectedQty = (float) $restaurantInventoryCountItem->expected_qty;
        $variance = round($countedQty - $expectedQty, 3);

        $restaurantInventoryCountItem->forceFill([
            'counted_qty' => $countedQty,
            'variance_qty' => $variance,
            'reason_code' => $request->input('reason_code'),
        ])->save();

        return (new RestaurantInventoryCountItemResource($restaurantInventoryCountItem))->response();
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

        $count = $this->action->submit($actor, $restaurantInventoryCount);

        return (new RestaurantInventoryCountResource($count->load('items')))->response();
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

        $count = $this->action->approve($actor, $restaurantInventoryCount);

        return (new RestaurantInventoryCountResource($count->load('items')))->response();
    }
}
