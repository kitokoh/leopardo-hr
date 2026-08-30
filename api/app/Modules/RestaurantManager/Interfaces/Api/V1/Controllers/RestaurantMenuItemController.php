<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantMenuItemRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantMenuItemRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantMenuItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-304 (#6185) — CRUD des lignes de menu (ressource nested).
 *
 * Les lignes vivent sous `/restaurant/menus/{restaurantMenu}/items` : le
 * menu parent est la borne d'isolation — un `{restaurantMenu}` d'un autre
 * tenant renvoie 404 (jamais 403), de même qu'une ligne dont le `menu_id`
 * ne correspond pas au menu de la route. Le contrôle `company_id` précède
 * systématiquement l'appel à `RestaurantMenuItemPolicy`.
 */
class RestaurantMenuItemController extends Controller
{
    public function index(Request $request, RestaurantMenu $restaurantMenu): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        if ($actor->cannot('viewAny', RestaurantMenuItem::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $items = RestaurantMenuItem::query()
            ->where('menu_id', $restaurantMenu->id)
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage);

        return RestaurantMenuItemResource::collection($items)->response();
    }

    public function store(StoreRestaurantMenuItemRequest $request, RestaurantMenu $restaurantMenu): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantMenuItem::class)) {
            abort(403);
        }

        $item = RestaurantMenuItem::query()->create([
            ...$request->validated(),
            'menu_id' => $restaurantMenu->id,
        ]);

        return (new RestaurantMenuItemResource($item))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantMenu $restaurantMenu, RestaurantMenuItem $restaurantMenuItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id
            || $restaurantMenuItem->menu_id !== $restaurantMenu->id
            || $restaurantMenuItem->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        return (new RestaurantMenuItemResource($restaurantMenuItem))->response();
    }

    public function update(UpdateRestaurantMenuItemRequest $request, RestaurantMenu $restaurantMenu, RestaurantMenuItem $restaurantMenuItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id
            || $restaurantMenuItem->menu_id !== $restaurantMenu->id
            || $restaurantMenuItem->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantMenuItem)) {
            abort(403);
        }

        $restaurantMenuItem->update($request->validated());

        return (new RestaurantMenuItemResource($restaurantMenuItem))->response();
    }

    public function destroy(Request $request, RestaurantMenu $restaurantMenu, RestaurantMenuItem $restaurantMenuItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id
            || $restaurantMenuItem->menu_id !== $restaurantMenu->id
            || $restaurantMenuItem->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantMenuItem)) {
            abort(403);
        }

        $restaurantMenuItem->delete();

        return new JsonResponse(null, 204);
    }
}
