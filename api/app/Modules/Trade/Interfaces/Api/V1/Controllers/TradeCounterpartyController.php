<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Interfaces\Api\V1\Requests\StoreCounterpartyRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\UpdateCounterpartyRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeCounterpartyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contreparties Trade — BC-36 TRADE, #9779.
 *
 * ## Le trou que ce controleur ferme
 *
 * `TradeCounterparty`, sa table et sa factory existaient depuis TRADE-003/#8478,
 * mais **aucune route ne les exposait** — ni ici, ni ailleurs dans `routes/`.
 * Or `POST /trade/rfqs/{rfq}/send` exige des `counterparty_ids` (`min:1`) :
 * sans ce controleur, un tenant reel ne pouvait PAS diffuser une demande de
 * prix. Le seul moyen d'obtenir une contrepartie etait le factory de test.
 *
 * C'est le meme motif que le catalogue avant #9767 : un modele complet que
 * rien n'expose.
 *
 * ## Isolation tenant
 *
 * Perimetre applique par `BelongsToCompany` : une contrepartie d'une autre
 * entreprise est **introuvable (404)**, jamais 403. Le `company_id` n'est
 * jamais lu depuis la requete.
 *
 * ## Suppression logique
 *
 * `DELETE` pose `deleted_at` : les RFQ diffusees et les offres emises citent la
 * contrepartie, une suppression dure casserait l'historique commercial. La
 * contrepartie disparait des listes par defaut et reste consultable en detail.
 */
final class TradeCounterpartyController
{
    /**
     * GET /trade/counterparties — liste paginee des contreparties du tenant.
     *
     * Filtres : `role` (un role metier parmi l'enum), `verification_status`,
     * `q` (nom). `q` echappe les jokers SQL : sans cela, chercher « 100% »
     * ramenerait tout l'annuaire (meme correctif que le catalogue, #9767).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeCounterparty::query()->orderBy('name');

        if ($request->filled('role')) {
            // `roles` est un tableau jsonb : `whereJsonContains` interroge
            // l'appartenance, `where('roles', ...)` comparerait le document
            // entier et ne matcherait rien.
            $query->whereJsonContains('roles', (string) $request->string('role'));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', (string) $request->string('verification_status'));
        }

        if ($request->filled('q')) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->string('q')).'%';

            // `ilike` n'existe qu'en PostgreSQL ; ailleurs, repli sur `like`
            // (SQLite est insensible a la casse sur ASCII par defaut).
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

            $query->where('name', $operator, $term);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeCounterpartyResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    /**
     * POST /trade/counterparties — creation.
     *
     * Une contrepartie nait `none` : elle ne devient `verified` que par l'acte
     * de verification trace, jamais a la creation.
     */
    public function store(StoreCounterpartyRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $counterparty = TradeCounterparty::query()->create([
            ...$validated,
            'roles' => array_values(array_unique($validated['roles'])),
            'verification_status' => $validated['verification_status'] ?? 'none',
        ]);

        return new JsonResponse(
            ['data' => TradeCounterpartyResource::make($counterparty->refresh())->resolve()],
            201,
        );
    }

    /**
     * GET /trade/counterparties/{counterparty} — detail.
     */
    public function show(TradeCounterparty $counterparty): JsonResponse
    {
        return new JsonResponse(['data' => TradeCounterpartyResource::make($counterparty)->resolve()]);
    }

    /**
     * PUT /trade/counterparties/{counterparty} — mise a jour des champs editables.
     *
     * `verification_status` n'est pas dans les champs acceptes : il est porte
     * par l'endpoint de verification, qui trace QUI et QUAND.
     */
    public function update(UpdateCounterpartyRequest $request, TradeCounterparty $counterparty): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        if (isset($validated['roles'])) {
            $validated['roles'] = array_values(array_unique($validated['roles']));
        }

        $counterparty->update($validated);

        return new JsonResponse(['data' => TradeCounterpartyResource::make($counterparty->refresh())->resolve()]);
    }

    /**
     * DELETE /trade/counterparties/{counterparty} — suppression LOGIQUE.
     *
     * Idempotent : supprimer une contrepartie deja supprimee renvoie 404 (elle
     * n'est plus dans le perimetre), ce qui reste sur.
     */
    public function destroy(TradeCounterparty $counterparty): JsonResponse
    {
        $counterparty->delete();

        return new JsonResponse(['data' => ['id' => $counterparty->id, 'deleted' => true]]);
    }
}
