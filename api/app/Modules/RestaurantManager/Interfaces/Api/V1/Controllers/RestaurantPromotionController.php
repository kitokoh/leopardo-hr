<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPromotionRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantPromotionRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPromotionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-607 (#6212) — CRUD des promotions RestaurantManager.
 *
 * La validation serveur (bornes percent/amount, fenêtre, minimum, plafond
 * d'utilisations) est portée par les Requests ; l'APPLICATION d'un code dans
 * l'addition est déjà assurée par BillCalculator::calculateWithPromotion()
 * (RESTO-405, #6192) qui résout la promotion, vérifie sa validité et borne la
 * remise au sous-total — ce CRUD alimente ce moteur (usage max, fenêtres).
 */
class RestaurantPromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantPromotion::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $promotions = RestaurantPromotion::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantPromotionResource::collection($promotions)->response();
    }

    public function store(StoreRestaurantPromotionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantPromotion::class)) {
            abort(403);
        }

        $promotion = RestaurantPromotion::query()->create($request->validated());

        return (new RestaurantPromotionResource($promotion))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantPromotion $restaurantPromotion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPromotion->company_id) {
            abort(404);
        }

        return (new RestaurantPromotionResource($restaurantPromotion))->response();
    }

    public function update(UpdateRestaurantPromotionRequest $request, RestaurantPromotion $restaurantPromotion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPromotion->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPromotion)) {
            abort(403);
        }

        $restaurantPromotion->update($request->validated());

        return (new RestaurantPromotionResource($restaurantPromotion))->response();
    }

    public function destroy(Request $request, RestaurantPromotion $restaurantPromotion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPromotion->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantPromotion)) {
            abort(403);
        }

        $restaurantPromotion->delete();

        return new JsonResponse(null, 204);
    }
}
