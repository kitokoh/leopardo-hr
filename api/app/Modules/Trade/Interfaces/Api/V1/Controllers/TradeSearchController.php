<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Infrastructure\Services\TradeSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche transverse import-export — BC-36 TRADE, #9781.
 *
 * `GET /api/v1/trade/search?q=...&limit=...`
 *
 * ## Pourquoi une route dédiée plutôt qu'un `q` sur chaque liste
 *
 * Les listes existantes (`/products`, `/rfqs`, `/quotes`) répondent à « je
 * sais ce que je cherche ». Cette route répond à l'inverse : « je ne sais plus
 * où c'est ». Elle interroge les trois référentiels en une requête et rend des
 * groupes, ce qui permet à l'écran de proposer un seul champ de recherche.
 *
 * ## Refus explicite d'une requête trop courte
 *
 * `q` vide ou < 2 caractères → **422**, jamais un dump de la base. Une
 * recherche d'un caractère ramènerait presque tout le répertoire : ce n'est
 * pas une recherche, c'est un export déguisé.
 */
final class TradeSearchController
{
    public function __construct(
        private readonly TradeSearchService $search,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if (mb_strlen($query) < TradeSearchService::MIN_QUERY_LENGTH) {
            return new JsonResponse([
                'message' => 'La recherche doit contenir au moins '.TradeSearchService::MIN_QUERY_LENGTH.' caractères.',
                'errors' => [
                    'q' => ['La recherche doit contenir au moins '.TradeSearchService::MIN_QUERY_LENGTH.' caractères.'],
                ],
            ], 422);
        }

        $limit = min(max($request->integer('limit', 10), 1), TradeSearchService::MAX_LIMIT);

        return new JsonResponse($this->search->search($query, $limit));
    }
}
