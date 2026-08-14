<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CnssDeclarationGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Issue #1830 — déclaration CNSS mensuelle Côte d'Ivoire :
 * GET /api/v1/payroll-runs/{run}/declarations/cnss-ci → CSV.
 *
 * Réservé aux managers avec rôle principal ou comptable. Isolation tenant :
 * tout run d'une autre entreprise → 404 (pas de fuite d'information).
 * Pays détecté via payroll_run.country_code — 422 si ≠ CI.
 */
class CnssDeclarationController extends Controller
{
    public function __construct(private readonly CnssDeclarationGenerator $generator) {}

    public function download(PayrollRun $payrollRun, Request $request): Response|JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager() || ! ($actor->isPrincipal() || $actor->isComptable())) {
            abort(403);
        }

        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }

        if (strtoupper((string) $payrollRun->country_code) !== 'CI') {
            return response()->json([
                'message' => 'La déclaration CNSS CI ne s\'applique qu\'aux runs de paie ivoiriens (country_code=CI).',
            ], 422);
        }

        $csv = $this->generator->generate($payrollRun);
        $totals = $this->generator->totals($payrollRun);

        $filename = sprintf(
            'CNSS_CI_%s_%s.csv',
            $payrollRun->period_start->format('Y-m'),
            now()->format('Ymd')
        );

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            'cnss-ci-declaration.csv'
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'X-CNSS-Totals' => json_encode([
                'slips' => $totals['slips'],
                'assiette_plafonnee' => $totals['assiette_plafonnee'],
                'retraite_salariale' => $totals['retraite_salariale'],
                'total_patronal' => $totals['total_patronal'],
            ]),
        ]);
    }
}
