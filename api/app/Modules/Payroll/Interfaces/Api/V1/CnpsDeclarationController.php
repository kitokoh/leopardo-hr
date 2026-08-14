<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CnpsDeclarationGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Issue #1823 — déclaration CNPS mensuelle Cameroun (DAS) :
 * GET /api/v1/payroll-runs/{run}/declarations/cnps-cm → CSV.
 *
 * Réservé aux managers avec rôle principal ou comptable. Isolation tenant :
 * tout run d'une autre entreprise → 404 (pas de fuite d'information).
 */
class CnpsDeclarationController extends Controller
{
    public function __construct(private readonly CnpsDeclarationGenerator $generator) {}

    public function download(PayrollRun $payrollRun, Request $request): Response
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager() || ! ($actor->isPrincipal() || $actor->isComptable())) {
            abort(403);
        }

        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }

        $csv = $this->generator->generate($payrollRun);
        $totals = $this->generator->totals($payrollRun);

        $filename = sprintf(
            'CNPS_CM_DAS_%s_%s.csv',
            $payrollRun->period_start->format('Y-m'),
            now()->format('Ymd')
        );

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            'cnps-cm-das.csv'
        );

        $response = response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'X-CNPS-Totals' => json_encode([
                'slips' => $totals['slips'],
                'assiette_plafonnee' => $totals['assiette_plafonnee'],
                'vieillesse_salariale' => $totals['vieillesse_salariale'],
                'total_patronal' => $totals['total_patronal'],
            ]),
        ]);

        return $response;
    }
}
