<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicDirectoryCache;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantBranchPublicProfileRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantBranchPublicProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * RESTO-901 (#7746) — Gestion du profil public d'une succursale (opt-in).
 *
 * `GET/PUT /restaurant/branches/{branch}/public-profile` — pile tenant :
 * toute résolution d'un `{restaurantBranch}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) ; l'autorisation
 * par branche est tranchée par `RestaurantBranchPolicy` (pattern
 * ChecksRestaurantBranchAccess, #7599).
 *
 * Le `public_slug` est unique GLOBAL (annuaire cross-tenant) : fourni, il est
 * validé (slugifié + unicité) ; absent alors que `is_public` passe à true, il
 * est généré depuis le nom de la branche avec suffixe numérique en cas de
 * collision. Toute mutation purge le cache public du profil (ancien et
 * nouveau slug).
 */
class RestaurantBranchPublicProfileController extends Controller
{
    public function __construct(
        private readonly RestaurantPublicDirectoryCache $publicCache,
    ) {}

    public function show(Request $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $restaurantBranch)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return (new RestaurantBranchPublicProfileResource($restaurantBranch))->response();
    }

    public function update(UpdateRestaurantBranchPublicProfileRequest $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantBranch)) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $previousSlug = $restaurantBranch->public_slug;

        $isPublic = array_key_exists('is_public', $validated)
            ? (bool) $validated['is_public']
            : (bool) $restaurantBranch->is_public;

        $slug = array_key_exists('public_slug', $validated)
            ? $validated['public_slug']
            : $restaurantBranch->public_slug;

        if ($isPublic && (! is_string($slug) || $slug === '')) {
            $validated['public_slug'] = $this->generateUniquePublicSlug($restaurantBranch);
        }

        $restaurantBranch->fill($validated);
        $restaurantBranch->save();

        // Invalidation du cache public : ancien slug (changement/dépublication)
        // ET slug courant (contenu éditorial modifié).
        $this->publicCache->forget($previousSlug);
        $this->publicCache->forget($restaurantBranch->public_slug);

        return (new RestaurantBranchPublicProfileResource($restaurantBranch))->response();
    }

    /**
     * Génère un slug public UNIQUE GLOBAL depuis le nom de la branche :
     * base slugifiée, puis suffixe numérique (-2, -3, ...) en cas de
     * collision cross-tenant, avec repli aléatoire borné (jamais de boucle
     * infinie). La vérification d'unicité est volontairement hors scope
     * tenant (`withoutGlobalScope('company')`) : l'annuaire est global.
     */
    private function generateUniquePublicSlug(RestaurantBranch $branch): string
    {
        $base = Str::slug((string) $branch->name);

        if ($base === '') {
            $base = 'restaurant';
        }

        $base = Str::limit($base, 140, '');

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            $candidate = $attempt === 1 ? $base : $base.'-'.$attempt;

            $taken = RestaurantBranch::query()
                ->withoutGlobalScope('company')
                ->where('public_slug', $candidate)
                ->where('id', '!=', $branch->id)
                ->exists();

            if (! $taken) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(8));
    }
}
