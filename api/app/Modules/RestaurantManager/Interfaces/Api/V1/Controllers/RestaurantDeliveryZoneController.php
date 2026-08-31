<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantDeliveryZoneRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantDeliveryZoneRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantDeliveryZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-604 (#6209) — Zones de livraison + frais.
 *
 * CRUD des zones (frais en minor units, commande minimum) + `quote` :
 * calcul serveur de l'éligibilité et des frais pour un montant de commande
 * (critère d'acceptation : « frais calculés serveur selon la zone »).
 */
class RestaurantDeliveryZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantDeliveryZone::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $zones = RestaurantDeliveryZone::query()
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantDeliveryZoneResource::collection($zones)->response();
    }

    public function store(StoreRestaurantDeliveryZoneRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantDeliveryZone::class)) {
            abort(403);
        }

        $zone = RestaurantDeliveryZone::query()->create($request->validated());

        return (new RestaurantDeliveryZoneResource($zone))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantDeliveryZone $restaurantDeliveryZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryZone->company_id) {
            abort(404);
        }

        return (new RestaurantDeliveryZoneResource($restaurantDeliveryZone))->response();
    }

    public function update(UpdateRestaurantDeliveryZoneRequest $request, RestaurantDeliveryZone $restaurantDeliveryZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryZone->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantDeliveryZone)) {
            abort(403);
        }

        $restaurantDeliveryZone->update($request->validated());

        return (new RestaurantDeliveryZoneResource($restaurantDeliveryZone))->response();
    }

    public function destroy(Request $request, RestaurantDeliveryZone $restaurantDeliveryZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryZone->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantDeliveryZone)) {
            abort(403);
        }

        $restaurantDeliveryZone->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * Devis de livraison : éligibilité + frais pour un montant de commande.
     */
    public function quote(Request $request, RestaurantDeliveryZone $restaurantDeliveryZone): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryZone->company_id) {
            abort(404);
        }

        $request->validate([
            'order_total_minor' => ['required', 'integer', 'min:0'],
        ]);

        $orderTotal = (int) $request->input('order_total_minor');
        $minOrder = $restaurantDeliveryZone->min_order_minor;
        $eligible = $minOrder === null || $orderTotal >= (int) $minOrder;

        return response()->json([
            'data' => [
                'zone_id' => $restaurantDeliveryZone->id,
                'order_total_minor' => $orderTotal,
                'min_order_minor' => $minOrder,
                'fee_minor' => $eligible ? (int) $restaurantDeliveryZone->fee_minor : 0,
                'eligible' => $eligible,
            ],
        ]);
    }
}
