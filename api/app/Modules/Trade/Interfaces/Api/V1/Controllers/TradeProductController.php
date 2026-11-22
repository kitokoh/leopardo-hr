<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Domain\Models\TradeProduct;
use App\Modules\Trade\Interfaces\Api\V1\Requests\StoreTradeProductRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\UpdateTradeProductRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue produits B2B — BC-36 TRADE, #9767.
 *
 * ## Le trou que ce contrôleur ferme
 *
 * `TradeProduct`, sa table et sa factory existaient depuis TRADE-002/#8477,
 * mais **aucune route ne les exposait** : un vendeur activé sur la verticale
 * `trade` pouvait émettre des offres et répondre à des RFQ, sans jamais
 * pouvoir créer un seul produit. Les lignes de RFQ/offres acceptent un
 * `trade_product_id` qui ne pouvait donc jamais être renseigné.
 *
 * ## Isolation tenant
 *
 * Le périmètre est appliqué par `BelongsToCompany` : un produit d'une autre
 * entreprise est **introuvable (404)**, jamais 403 — l'API ne révèle pas son
 * existence. Le `company_id` n'est jamais lu depuis la requête.
 *
 * ## Archivage, pas suppression
 *
 * `POST /trade/products/{product}/archive` pose `status = archived`. Aucune
 * suppression dure : les RFQ et offres passées référencent le produit, et un
 * `DELETE` casserait leur historique. Un produit archivé reste lisible en
 * détail mais sort des listes par défaut.
 */
final class TradeProductController
{
    /**
     * GET /trade/products — liste paginée du catalogue du tenant.
     *
     * Filtres : `status` (défaut : tous, y compris archivés — l'écran de
     * gestion doit pouvoir les retrouver), `category`, `q` (nom).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeProduct::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $request->string('category'));
        }

        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->string('q')).'%';
            $query->where('name', 'ilike', $term);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeProductResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    /**
     * POST /trade/products — création.
     *
     * `company_id` est posé par le trait `BelongsToCompany` depuis le contexte
     * tenant : il n'est jamais accepté du client (anti-spoof #7646).
     */
    public function store(StoreTradeProductRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $product = TradeProduct::query()->create([
            ...$validated,
            'unit' => $validated['unit'] ?? 'unit',
            'status' => $validated['status'] ?? 'active',
        ]);

        return new JsonResponse(
            ['data' => TradeProductResource::make($product->refresh())->resolve()],
            201,
        );
    }

    /**
     * GET /trade/products/{product} — détail.
     */
    public function show(TradeProduct $product): JsonResponse
    {
        return new JsonResponse(['data' => TradeProductResource::make($product)->resolve()]);
    }

    /**
     * PUT /trade/products/{product} — mise à jour des champs éditables.
     */
    public function update(UpdateTradeProductRequest $request, TradeProduct $product): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $product->update($validated);

        return new JsonResponse(['data' => TradeProductResource::make($product->refresh())->resolve()]);
    }

    /**
     * POST /trade/products/{product}/archive — archivage logique.
     *
     * Idempotent : archiver un produit déjà archivé renvoie 200 avec le même
     * état, sans erreur — l'écran peut réessayer sans risque.
     */
    public function archive(TradeProduct $product): JsonResponse
    {
        if (! $product->isArchived()) {
            $product->update(['status' => 'archived']);
        }

        return new JsonResponse(['data' => TradeProductResource::make($product->refresh())->resolve()]);
    }
}
