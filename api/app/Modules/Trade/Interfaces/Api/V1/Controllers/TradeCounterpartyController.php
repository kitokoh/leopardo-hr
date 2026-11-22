<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Domain\Enums\TradeVerificationStatus;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Interfaces\Api\V1\Requests\StoreTradeCounterpartyRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\UpdateTradeCounterpartyRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\VerifyTradeCounterpartyRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeCounterpartyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contreparties Trade — clients, fournisseurs, transporteurs, transitaires,
 * courtiers en douane, inspecteurs (BC-36 TRADE, #9780).
 *
 * ## Le trou que ce contrôleur ferme
 *
 * `trade_counterparties`, son modèle, sa factory et ses enums existaient depuis
 * TRADE-003/#8478, mais **aucune route ne les exposait** : un tenant activé sur
 * `trade` pouvait émettre des RFQ et des offres sans jamais pouvoir créer un
 * fournisseur ou un client. `trade_deal.trade_counterparty_id` et
 * `trade_rfq_invitations.trade_counterparty_id` ne pouvaient donc jamais être
 * renseignés — le cycle commercial s'arrêtait à la première étape.
 *
 * ## Isolation tenant
 *
 * Le périmètre est appliqué par `BelongsToCompany` : une contrepartie d'une
 * autre entreprise est **introuvable (404)**, jamais 403 — l'API ne révèle pas
 * son existence. Le `company_id` n'est jamais lu depuis la requête.
 *
 * ## Vérification : un acte, pas un champ
 *
 * `POST /trade/counterparties/{counterparty}/verify` est séparé de la mise à
 * jour. Il trace QUI a vérifié et QUAND, et exige un motif pour toute décision.
 * `verified` signifie « vérifié par l'équipe » — jamais « vérifié
 * automatiquement » (aucun service externe n'est souscrit en v1).
 *
 * ## Suppression douce
 *
 * `DELETE` pose `deleted_at`. Aucune suppression dure : les dossiers, RFQ et
 * offres passés référencent la contrepartie, et un `DELETE` physique casserait
 * leur historique.
 */
final class TradeCounterpartyController
{
    /**
     * GET /trade/counterparties — liste paginée du tenant.
     *
     * Filtres : `role` (un rôle métier), `country` (ISO alpha-2),
     * `verification_status`, `q` (nom, email ou ville).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeCounterparty::query()->orderByDesc('id');

        if ($request->filled('role')) {
            $role = (string) $request->string('role');

            // `roles` est un tableau jsonb : on filtre par appartenance, pas par
            // égalité — une contrepartie peut être à la fois fabricant et
            // fournisseur, et doit ressortir dans les deux filtres.
            $query->whereJsonContains('roles', $role);
        }

        if ($request->filled('country')) {
            $query->where('country', strtoupper((string) $request->string('country')));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', (string) $request->string('verification_status'));
        }

        if ($request->filled('q')) {
            // Échappement du motif : un `%` saisi par l'utilisateur est un joker
            // SQL, pas un caractère — sans échappement, chercher « 100% »
            // ramènerait tout le répertoire.
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->string('q')).'%';

            // `ilike` n'existe qu'en PostgreSQL ; ailleurs on retombe sur `like`.
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

            $query->where(static function ($builder) use ($operator, $term): void {
                $builder->where('name', $operator, $term)
                    ->orWhere('email', $operator, $term)
                    ->orWhere('city', $operator, $term);
            });
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeCounterpartyResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    /**
     * POST /trade/counterparties — création.
     *
     * `company_id` est posé par `BelongsToCompany` depuis le contexte tenant.
     * Le statut de vérification démarre toujours à `none` : on ne se déclare
     * pas vérifié en créant sa fiche.
     */
    public function store(StoreTradeCounterpartyRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $counterparty = TradeCounterparty::query()->create([
            ...$validated,
            'roles' => $request->roles(),
            'verification_status' => TradeVerificationStatus::None->value,
        ]);

        return new JsonResponse(
            ['data' => TradeCounterpartyResource::make($counterparty->refresh())->resolve()],
            201,
        );
    }

    /**
     * GET /trade/counterparties/{counterparty} — détail.
     */
    public function show(TradeCounterparty $counterparty): JsonResponse
    {
        return new JsonResponse(['data' => TradeCounterpartyResource::make($counterparty)->resolve()]);
    }

    /**
     * PUT /trade/counterparties/{counterparty} — mise à jour des champs éditables.
     */
    public function update(UpdateTradeCounterpartyRequest $request, TradeCounterparty $counterparty): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        if (array_key_exists('roles', $validated)) {
            /** @var array<int, string> $roles */
            $roles = $validated['roles'];
            $validated['roles'] = array_values(array_unique($roles));
        }

        $counterparty->update($validated);

        return new JsonResponse(['data' => TradeCounterpartyResource::make($counterparty->refresh())->resolve()]);
    }

    /**
     * POST /trade/counterparties/{counterparty}/verify — acte de vérification.
     *
     * Trace l'auteur et l'instant. `pending` efface la décision précédente
     * (retour à l'instruction) ; `verified`/`rejected` exigent un motif.
     */
    public function verify(VerifyTradeCounterpartyRequest $request, TradeCounterparty $counterparty): JsonResponse
    {
        /** @var array{status: string, note?: string|null} $validated */
        $validated = $request->validated();

        $status = TradeVerificationStatus::from($validated['status']);
        $employeeId = $request->user()?->id;

        $counterparty->update([
            'verification_status' => $status->value,
            'verification_note' => $validated['note'] ?? null,
            // Une décision est datée et attribuée ; un retour à `pending` efface
            // la trace précédente — elle n'a plus de sens une fois la décision
            // retirée.
            'verified_by_employee_id' => $status === TradeVerificationStatus::Pending ? null : $employeeId,
            'verified_at' => $status === TradeVerificationStatus::Pending ? null : now(),
        ]);

        return new JsonResponse(['data' => TradeCounterpartyResource::make($counterparty->refresh())->resolve()]);
    }

    /**
     * DELETE /trade/counterparties/{counterparty} — suppression douce.
     *
     * Aucune suppression physique : les dossiers et offres passés référencent
     * la contrepartie.
     */
    public function destroy(TradeCounterparty $counterparty): JsonResponse
    {
        $counterparty->delete();

        return new JsonResponse(null, 204);
    }
}
