<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Actions\AccountingActivationService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\CompleteActivationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Activation guidée du module Comptabilité — issue #5288 (wizard).
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) : `comptable` et
 * `principal` — les routes portent le middleware `api.manager:comptable,principal`
 * (défense en profondeur, même garde que /accounting/settings).
 *
 * Isolation tenant : la compagnie est résolue depuis l'employé authentifié de
 * la requête, jamais par un id d'URL — aucune fuite cross-tenant possible
 * (fail-closed #3727). Idempotence : l'activation est rejouable sans doublon
 * (données de démo repérées par marqueurs `metadata`).
 */
final class AccountingActivationController extends Controller
{
    public function __construct(private readonly AccountingActivationService $activation) {}

    /**
     * État d'activation du tenant courant (check-list du wizard).
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->activation->status($this->companyId($request)),
        ]);
    }

    /**
     * Exécute l'activation complète : paramétrage + contact de test + facture
     * d'exemple (idempotent).
     */
    public function complete(CompleteActivationRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->activation->complete(
                $this->companyId($request),
                $this->companyCountry(),
                $request->validated(),
            ),
        ]);
    }

    private function companyId(Request $request): string
    {
        // getAttribute() : compagnie de l'employé authentifié (même pattern
        // que AccountingSettingsController — jamais de fuite cross-tenant).
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }

    private function companyCountry(): ?string
    {
        if (! app()->bound('current_company')) {
            return null;
        }

        return currentCompany()->country ?? null;
    }
}
