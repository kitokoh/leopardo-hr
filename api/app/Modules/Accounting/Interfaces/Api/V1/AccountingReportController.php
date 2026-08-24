<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Infrastructure\Services\VatDeclarationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Rapports du module Comptabilité — issue #5271.
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) : `comptable` et
 * `principal` — les routes portent le middleware `api.manager:comptable,principal`.
 *
 * Isolation tenant : la ressource est agrégée pour la compagnie courante de la
 * requête (jamais d'id d'URL) — aucune fuite cross-tenant possible (fail-closed
 * #3727).
 */
class AccountingReportController extends Controller
{
    public function __construct(
        private readonly VatDeclarationService $vatDeclarationService,
    ) {}

    /**
     * Déclaration TVA simplifiée par période (YYYY-MM).
     */
    public function vatDeclaration(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', 'date_format:Y-m'],
            'format' => ['nullable', 'string', 'in:json,csv'],
        ]);

        $period = (string) ($validated['period'] ?? now()->format('Y-m'));

        if (! app()->bound('current_company')) {
            abort(403, 'Tenant context missing.');
        }

        $declaration = $this->vatDeclarationService->declaration(currentCompany(), $period);

        if (($validated['format'] ?? 'json') === 'csv') {
            return $this->vatDeclarationService->toCsv($declaration);
        }

        return response()->json([
            'data' => $declaration,
        ]);
    }
}
