<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Infrastructure\Services\Mobile\RestaurantMobileRiderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-802 (#6223) — API mobile livreur (tournées, statuts, navigation).
 *
 * Authentifiée Sanctum ; le livreur est résolu par `employee_id` sur
 * RestaurantDeliveryRider (référence RH par valeur). 404 sûr cross-tenant.
 */
class RestaurantMobileRiderController extends Controller
{
    public function __construct(private readonly RestaurantMobileRiderService $service)
    {
    }

    public function deliveries(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->service->myDeliveries($actor)]);
    }

    public function show(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $this->service->delivery($actor, $restaurantDelivery);

        return response()->json(['data' => $data]);
    }

    public function outForDelivery(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $delivery = $this->service->outForDelivery($actor, $restaurantDelivery);

        return response()->json(['data' => [
            'id' => $delivery->id,
            'status' => $delivery->status->value,
        ]]);
    }

    public function deliver(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $delivery = $this->service->deliver($actor, $restaurantDelivery);

        return response()->json(['data' => [
            'id' => $delivery->id,
            'status' => $delivery->status->value,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
        ]]);
    }
}
