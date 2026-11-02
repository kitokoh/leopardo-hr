<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Services\PublicActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * COULOIR 16 — M3 (#9105) · Activités, favoris et historique du client public.
 *
 * `auth:user_api` obligatoire : le client est un `users.id` (identité
 * plateforme), pas un employé. Aucun middleware `tenant` : un client agit sur
 * plusieurs entreprises, et le tenant est résolu côté serveur.
 *
 * ## Ce que ces réponses ne contiennent jamais
 *
 * Aucun `company_id`, aucun identifiant interne, aucun nom d'employé, aucun
 * prix d'achat. Le client voit SA référence, SON statut, le nom commercial du
 * professionnel et une date. C'est tout ce dont l'onglet a besoin.
 *
 * ## 404 uniforme
 *
 * Un favori sur un profil inexistant, dépublié ou suspendu renvoie le même
 * `PROFILE_NOT_AVAILABLE` : impossible d'énumérer les profils en observant les
 * codes de statut.
 */
class PublicActivityController extends Controller
{
    public function __construct(private readonly PublicActivityService $activities) {}

    /**
     * GET /api/v1/user/activities — activités du client, revalidées côté tenant.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $limit = (int) $request->integer('per_page', PublicActivityService::MAX_ACTIVITIES);

        return new JsonResponse([
            'data' => $this->activities->activitiesFor($client, $limit)->values(),
            'meta' => [
                // L'onglet n'affiche QUE ces sections : un module sans contrat
                // n'y figure pas, donc aucun onglet vide par construction.
                'sections' => $this->activities->activitySections(),
            ],
        ]);
    }

    /**
     * GET /api/v1/user/favorites — favoris du client.
     */
    public function favorites(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $limit = (int) $request->integer('per_page', PublicActivityService::MAX_FAVORITES);

        return new JsonResponse([
            'data' => $this->activities->favoritesFor($client, $limit)->values(),
        ]);
    }

    /**
     * POST /api/v1/user/favorites — ajouter un favori (idempotent).
     */
    public function storeFavorite(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $validated = $request->validate([
            'target_type' => ['required', 'string', 'max:40'],
            'target_slug' => ['required', 'string', 'max:160'],
        ]);

        try {
            $result = $this->activities->addFavorite(
                $client,
                (string) $validated['target_type'],
                (string) $validated['target_slug'],
            );
        } catch (\InvalidArgumentException) {
            // Uniforme : profil inexistant, dépublié, suspendu ou verticale
            // inactive → même réponse (anti-énumération).
            return new JsonResponse(['error' => 'PROFILE_NOT_AVAILABLE'], 404);
        }

        return new JsonResponse(
            ['data' => $result['favorite']],
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * DELETE /api/v1/user/favorites/{type}/{slug} — retirer un favori.
     *
     * Idempotent : retirer un favori absent renvoie 204, pas 404. L'intention
     * de la personne est satisfaite dans les deux cas, et un 404 l'obligerait à
     * interpréter un état qui ne la concerne pas.
     */
    public function destroyFavorite(string $type, string $slug): JsonResponse
    {
        /** @var User $client */
        $client = request()->user('user_api');

        $this->activities->removeFavorite($client, $type, $slug);

        return new JsonResponse(null, 204);
    }

    /**
     * GET /api/v1/user/search-history — dernières recherches.
     */
    public function searchHistory(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $limit = (int) $request->integer('per_page', PublicActivityService::MAX_HISTORY);

        return new JsonResponse([
            'data' => $this->activities->searchHistoryFor($client, $limit)->values(),
        ]);
    }

    /**
     * POST /api/v1/user/search-history — enregistrer une recherche.
     *
     * Aucune coordonnée n'est acceptée : `city` est une ville, pas une
     * position. La position précise n'est jamais conservée (règle de
     * confidentialité du couloir).
     */
    public function storeSearchHistory(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            'result_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $this->activities->recordSearch(
            $client,
            (string) $validated['query'],
            isset($validated['city']) ? (string) $validated['city'] : null,
            (int) ($validated['result_count'] ?? 0),
        );

        return new JsonResponse(null, 204);
    }

    /**
     * DELETE /api/v1/user/search-history — effacer l'historique.
     */
    public function clearSearchHistory(): JsonResponse
    {
        /** @var User $client */
        $client = request()->user('user_api');

        $deleted = $this->activities->clearSearchHistory($client);

        return new JsonResponse(['data' => ['deleted' => $deleted]]);
    }
}
