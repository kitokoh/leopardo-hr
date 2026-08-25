<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Infrastructure\Services\FinancialStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * États financiers — bilan et compte de résultat (issue #5422).
 *
 * RBAC (matrice comptabilité) : `comptable` et `principal` — les routes
 * portent le middleware `api.manager:principal,comptable`.
 *
 * Isolation tenant : la ressource est agrégée pour la compagnie de
 * l'utilisateur authentifié (`company_id` explicite, jamais d'id d'URL) —
 * aucune fuite cross-tenant possible (fail-closed #3727).
 */
final class AccountingStatementController extends Controller
{
    /**
     * GET /api/v1/accounting/statements/balance-sheet?year=YYYY
     *
     * Bilan d'une année civile (actif, passif, capitaux propres + résultat
     * net) avec l'invariant d'équilibre (balanced).
     */
    public function balanceSheet(Request $request, FinancialStatementService $service): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ], [
            'year.required' => __('accounting.validation.year_required'),
            'year.integer' => __('accounting.validation.year_integer'),
            'year.min' => __('accounting.validation.year_range'),
            'year.max' => __('accounting.validation.year_range'),
        ]);

        $companyId = (string) $request->user()?->company_id;
        if ($companyId === '') {
            abort(403, __('accounting.errors.wf_company_context'));
        }

        $year = (int) $validated['year'];
        $statement = $service->balanceSheet($companyId, $year);

        return response()->json([
            'data' => [
                'actif' => $statement['actif'],
                'passif' => $statement['passif'],
                'capitaux_propres' => $statement['capitaux'],
                'total_actif' => $statement['total_actif'],
                'total_passif' => $statement['total_passif_capitaux'],
                'resultat_net' => $statement['resultat_net'],
                'balanced' => $statement['balanced'],
            ],
            'meta' => ['year' => $year],
        ]);
    }

    /**
     * GET /api/v1/accounting/statements/income-statement?period=YYYY-MM
     *
     * Compte de résultat d'une période mensuelle (produits, charges,
     * résultat).
     */
    public function incomeStatement(Request $request, FinancialStatementService $service): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'period.required' => __('accounting.validation.period_required'),
            'period.regex' => __('accounting.validation.period_invalid'),
        ]);

        $companyId = (string) $request->user()?->company_id;
        if ($companyId === '') {
            abort(403, __('accounting.errors.wf_company_context'));
        }

        $period = (string) $validated['period'];
        $statement = $service->incomeStatement($companyId, $period);

        return response()->json([
            'data' => [
                'produits' => $statement['produits'],
                'charges' => $statement['charges'],
                'resultat' => $statement['resultat'],
            ],
            'meta' => ['period' => $period],
        ]);
    }
}
