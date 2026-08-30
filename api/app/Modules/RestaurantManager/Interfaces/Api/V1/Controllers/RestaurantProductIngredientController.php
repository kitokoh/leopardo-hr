<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantProductIngredientRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantProductIngredientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-302 (#6183) — Lignes de recette d'un produit (ressource imbriquée).
 *
 * Routes sous `/restaurant/products/{restaurantProduct}/ingredients` :
 * le produit parent est toujours résolu en premier (404 sûr cross-tenant),
 * puis l'autorisation est tranchée par `RestaurantProductIngredientPolicy`.
 * Un lien d'un autre produit (ou d'un autre tenant) renvoie 404.
 */
class RestaurantProductIngredientController extends Controller
{
    public function index(Request $request, RestaurantProduct $restaurantProduct): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $links = RestaurantProductIngredient::query()
            ->where('product_id', $restaurantProduct->id)
            ->orderBy('id')
            ->paginate($perPage);

        return RestaurantProductIngredientResource::collection($links)->response();
    }

    public function store(StoreRestaurantProductIngredientRequest $request, RestaurantProduct $restaurantProduct): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantProductIngredient::class)) {
            abort(403);
        }

        $link = RestaurantProductIngredient::query()->create(
            array_merge($request->validated(), ['product_id' => $restaurantProduct->id])
        );

        return (new RestaurantProductIngredientResource($link))->response()->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        RestaurantProduct $restaurantProduct,
        RestaurantProductIngredient $restaurantProductIngredient
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        if ($restaurantProductIngredient->product_id !== $restaurantProduct->id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantProductIngredient)) {
            abort(403);
        }

        $restaurantProductIngredient->delete();

        return new JsonResponse(null, 204);
    }
}
