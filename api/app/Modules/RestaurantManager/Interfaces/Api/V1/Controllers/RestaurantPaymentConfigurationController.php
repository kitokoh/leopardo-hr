<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPaymentConfigurationService;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #7728 (BC-25 RESTAURANT / BC-21) — état de configuration de l'ENCAISSEMENT
 * du restaurateur, dérivé de ses profils de paiement tenant (#7727).
 *
 * `GET /restaurant/payments/configuration` : indique si la carte en ligne
 * (profil `stripe_keys` actif) et le mobile money (sandbox ou production
 * feature-flaggée + profil actif) sont opérationnels — aucun secret exposé,
 * uniquement des indicateurs. Les profils eux-mêmes se gèrent dans
 * Réglages → Encaissements (`/billing/payment-profiles`).
 */
class RestaurantPaymentConfigurationController extends Controller
{
    use ChecksRestaurantBranchAccess;

    public function __construct(
        private readonly RestaurantPaymentConfigurationService $configuration,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // #7599/#7600 (guard RBAC) : la configuration d'encaissement est une
        // surface de gestion sensible du tenant (company-wide, sans branche)
        // — niveau `manage`, réservé au gérant/propriétaire (principal/rh,
        // principal seul dès que le scoping par succursale est actif).
        if (! $this->canManageBranchResource($actor, null)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return response()->json([
            'data' => $this->configuration->statusForCompany((string) $actor->company_id),
        ]);
    }
}
