<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicDirectoryCache;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantProductPublicationRequest;
use Illuminate\Http\JsonResponse;

/**
 * RESTO-901 (#7746) — Publication en ligne d'un produit du catalogue.
 *
 * `PATCH /restaurant/products/{product}/publication` : bascule
 * `is_published_online` (opt-in, défaut false). Seuls les produits publiés
 * ET disponibles apparaissent dans le menu du profil public. Résolution
 * cross-tenant → 404 (jamais 403) ; autorisation par branche via
 * `RestaurantProductPolicy::update()` (ChecksRestaurantBranchAccess).
 */
class RestaurantProductPublicationController extends Controller
{
    public function __construct(
        private readonly RestaurantPublicDirectoryCache $publicCache,
    ) {}

    public function update(UpdateRestaurantProductPublicationRequest $request, RestaurantProduct $restaurantProduct): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantProduct)) {
            abort(403);
        }

        $restaurantProduct->is_published_online = (bool) $request->validated('is_published_online');
        $restaurantProduct->save();

        // Purge du cache public du profil de la branche porteuse (un produit
        // company-wide, branch_id null, peut figurer sur plusieurs profils :
        // le TTL borné du cache couvre ce cas).
        $branch = $restaurantProduct->branch;
        $this->publicCache->forget($branch?->public_slug);

        return response()->json([
            'data' => [
                'id' => $restaurantProduct->id,
                'is_published_online' => (bool) $restaurantProduct->is_published_online,
            ],
        ]);
    }
}
