<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Actions\AccountingDashboardService;
use App\Modules\Accounting\Application\Actions\AccountingReportingSnapshotService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\AccountingDashboardRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tableaux de bord comptables — issue #5230.
 *
 * Rapports de pilotage (lecture seule) pour `comptable`/`principal` :
 * factures émises, encaissements, impayés (aging) et dépenses fournisseurs,
 * avec export CSV de la liste des impayés.
 *
 * Isolation tenant : la compagnie est résolue depuis l'employé authentifié de
 * la requête, jamais par un id d'URL (fail-closed #3727).
 */
final class AccountingDashboardController extends Controller
{
    public function __construct(private readonly AccountingDashboardService $dashboard) {}

    /**
     * Synthèse du tableau de bord pour la période demandée.
     *
     * Le bloc `data.snapshot` expose la fraîcheur (BC-22-D10, #6243) :
     * `source: "live"` tant qu'aucun snapshot n'est activé pour la période,
     * sinon `source: "snapshot"` + `version` + `refreshed_at`.
     */
    public function show(AccountingDashboardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $summary = $this->dashboard->summary(
            $this->companyId($request),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        $summary['snapshot'] = app(AccountingReportingSnapshotService::class)->metadata(
            $this->companyId($request),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return response()->json(['data' => $summary]);
    }

    /**
     * Export CSV de la liste des impayés (aging).
     */
    public function export(AccountingDashboardRequest $request): Response
    {
        $validated = $request->validated();

        return $this->dashboard->toOutstandingCsv(
            $this->companyId($request),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
