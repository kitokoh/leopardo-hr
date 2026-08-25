<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Exports\FecExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Export FEC — Fichier des Écritures Comptables (norme DGFiP, issue #5422).
 *
 * GET /api/v1/accounting/journal/export-fec?period=YYYY-MM
 *
 * Génère le fichier FEC de la période pour l'expert-comptable (13 colonnes,
 * CSV UTF-8). RBAC : principal/comptable (même périmètre que le journal).
 */
final class AccountingFecController extends Controller
{
    public function __construct(private readonly FecExporter $exporter) {}

    /**
     * Export FEC de la période demandée.
     */
    public function export(Request $request): Response|JsonResponse
    {
        $validated = Validator::make($request->only('period'), [
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'period.required' => __('accounting.validation.period_required'),
            'period.regex' => __('accounting.validation.period_invalid'),
        ])->validate();

        $period = (string) $validated['period'];
        $companyId = (string) ($request->user()?->getAttribute('company_id') ?? '');

        if (! AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->exists()) {
            return response()->json([
                'message' => __('accounting.fec_no_entries'),
                'code' => 'FEC_NO_ENTRIES',
            ], 422);
        }

        $csv = $this->exporter->export($companyId, $period);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fec-'.$period.'.csv"',
        ]);
    }
}
