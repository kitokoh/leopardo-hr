<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Actions\GenerateAbsenteeismReportAction;
use App\Modules\Platform\Application\Actions\GenerateHeadcountReportAction;
use App\Modules\Platform\Application\Actions\GeneratePayrollSummaryReportAction;
use App\Modules\Platform\Application\Actions\GenerateTrainingProgressReportAction;
use App\Modules\Platform\Application\Actions\GenerateTurnoverReportAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rapports RH cross-tenant pour le super-admin (contrat SPA admin,
 * issue #1764 : GET /v1/hr-reports — écran « Exports » du SPA).
 *
 * Types supportés (alignés sur le select du SPA) :
 *   headcount, turnover, absenteeism, payroll_summary, training_progress.
 *
 * Réponse : { data: { columns: string[], rows: array<string, mixed>[] } } —
 * le SPA rend `columns` comme en-têtes et `rows[col]` comme cellules.
 *
 * Contrôleur mince : la logique métier vit dans les Actions
 * Generate*ReportAction (issue #6569, audit DDD M1).
 */
class PlatformHrReportController extends Controller
{
    /** @var array<string, string> */
    private const TYPES = [
        'headcount' => 'headcount',
        'turnover' => 'turnover',
        'absenteeism' => 'absenteeism',
        'payroll_summary' => 'payroll_summary',
        'training_progress' => 'training_progress',
    ];

    public function __construct(
        private readonly GenerateHeadcountReportAction $headcount,
        private readonly GenerateTurnoverReportAction $turnover,
        private readonly GenerateAbsenteeismReportAction $absenteeism,
        private readonly GeneratePayrollSummaryReportAction $payrollSummary,
        private readonly GenerateTrainingProgressReportAction $trainingProgress,
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $report = match ($validated['type']) {
                'headcount' => $this->headcount->execute(),
                'turnover' => $this->turnover->execute($validated['start_date'], $validated['end_date']),
                'absenteeism' => $this->absenteeism->execute($validated['start_date'], $validated['end_date']),
                'payroll_summary' => $this->payrollSummary->execute($validated['start_date'], $validated['end_date']),
                'training_progress' => $this->trainingProgress->execute($validated['start_date'], $validated['end_date']),
                default => ['columns' => [], 'rows' => []],
            };
        } catch (RuntimeException $e) {
            throw new HttpException(503, $e->getMessage());
        }

        return response()->json(['data' => $report]);
    }
}
