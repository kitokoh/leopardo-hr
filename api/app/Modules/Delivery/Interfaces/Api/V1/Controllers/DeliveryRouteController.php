<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Application\Services\DeliveryRouteService;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryRouteAssignRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Requests\DeliveryRouteStoreRequest;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryRouteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API des tournées (DELIVERY-202, issue #6286) — RBAC manager
 * (`api.manager`) ; la matrice fine `delivery.dispatcher` est le scope de
 * BC-26-D05.
 *
 * Isolation tenant : company résolue depuis l'employé authentifié, jamais par
 * URL ; toutes les gardes (chevauchement, clôture) vivent dans le service
 * transactionnel avec verrou `SELECT FOR UPDATE`.
 */
final class DeliveryRouteController
{
    public function __construct(private readonly DeliveryRouteService $routes) {}

    public function store(DeliveryRouteStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $routeDate = \Illuminate\Support\Carbon::parse($validated['route_date']);

        $route = $this->routes->create(
            companyId: $this->companyId($request),
            routeDate: $routeDate,
            zone: $validated['zone'] ?? null,
            deliveryIds: array_map('intval', $validated['delivery_ids']),
        );

        return (new DeliveryRouteResource($route))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function assign(DeliveryRouteAssignRequest $request, int $route): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->routes->assign(
            routeId: $route,
            companyId: $this->companyId($request),
            driverId: (int) $validated['driver_id'],
            vehicleCode: $validated['vehicle_code'] ?? null,
        );

        return (new DeliveryRouteResource($updated))->response();
    }

    public function close(Request $request, int $route): JsonResponse
    {
        $closed = $this->routes->close($route, $this->companyId($request));

        return (new DeliveryRouteResource($closed))->response();
    }

    public function show(Request $request, int $route): JsonResponse
    {
        $found = DeliveryRoute::query()
            ->where('company_id', $this->companyId($request))
            ->whereKey($route)
            ->with('stops')
            ->first();

        if ($found === null) {
            abort(404);
        }

        return (new DeliveryRouteResource($found))->response();
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
