<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Exceptions\FiscalYearAlreadyClosedException;
use App\Modules\Accounting\Domain\Models\AccountingFiscalYear;
use App\Modules\Accounting\Infrastructure\Services\FiscalYearClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exercices comptables — issue #5422.
 *
 * - GET  /accounting/fiscal-years                  → liste des exercices ;
 * - POST /accounting/fiscal-years                  → ouverture d'un exercice ;
 * - POST /accounting/fiscal-years/{year}/close     → clôture (report à
 *   nouveau + périodes figées).
 *
 * Erreurs métier → 422 {message, code} ; isolation tenant via
 * BelongsToCompany + company_id porté par l'utilisateur authentifié.
 */
final class AccountingFiscalYearController extends Controller
{
    /**
     * GET /api/v1/accounting/fiscal-years
     */
    public function index(Request $request, FiscalYearClosingService $service): JsonResponse
    {
        $years = $service->list($this->companyId($request));

        return response()->json([
            'data' => $years->map(static fn (AccountingFiscalYear $year): array => [
                'year' => $year->year,
                'status' => $year->status,
                'closed_at' => $year->closed_at?->toIso8601String(),
                'closed_by' => $year->closed_by,
            ])->values(),
            'meta' => ['count' => $years->count()],
        ]);
    }

    /**
     * POST /api/v1/accounting/fiscal-years — ouvre l'exercice (idempotent).
     */
    public function store(Request $request, FiscalYearClosingService $service): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
        ], [
            'year.required' => __('accounting.validation.year_required'),
            'year.integer' => __('accounting.validation.year_integer'),
            'year.between' => __('accounting.validation.year_between'),
        ]);

        $year = $service->open(
            $this->companyId($request),
            (int) $validated['year'],
            $this->actorName($request),
        );

        return response()->json([
            'data' => [
                'year' => $year->year,
                'status' => $year->status,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/accounting/fiscal-years/{year}/close
     */
    public function close(Request $request, FiscalYearClosingService $service, int $year): JsonResponse
    {
        try {
            $result = $service->close(
                $this->companyId($request),
                $year,
                $this->actorName($request),
            );
        } catch (FiscalYearAlreadyClosedException) {
            return response()->json([
                'message' => __('accounting.fiscal_year_already_closed'),
                'code' => 'FISCAL_YEAR_ALREADY_CLOSED',
            ], 422);
        }

        return response()->json([
            'data' => [
                'year' => $year,
                'status' => AccountingFiscalYear::STATUS_CLOSED,
                'result' => $result['result'],
                'entry_count' => $result['entry_count'],
                'closed_periods' => $result['closed_periods'],
            ],
        ]);
    }

    private function companyId(Request $request): string
    {
        return (string) $request->user()?->company_id;
    }

    /**
     * Nom de l'acteur pour la trace d'audit (closed_by). Le modèle employé
     * n'expose pas d'attribut `name` : on compose prénom + nom, avec repli
     * sur 'system' pour les appels non authentifiés.
     */
    private function actorName(Request $request): string
    {
        $user = $request->user();

        if (! $user instanceof Employee) {
            return 'system';
        }

        $name = trim($user->first_name.' '.$user->last_name);

        return $name !== '' ? $name : 'system';
    }
}
