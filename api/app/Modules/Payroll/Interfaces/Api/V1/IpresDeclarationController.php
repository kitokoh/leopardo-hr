<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\IpresDeclarationGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Issue #1830 — déclaration IPRES/CSS mensuelle Sénégal :
 * GET /api/v1/payroll-runs/{run}/declarations/ipres-sn → CSV.
 *
 * Réservé aux managers avec rôle principal ou comptable. Isolation tenant :
 * tout run d'une autre entreprise → 404 (pas de fuite d'information).
 * Pays détecté via payroll_run.country_code — 422 si ≠ SN.
 */
class IpresDeclarationController extends Controller
{
    public function __construct(private readonly IpresDeclarationGenerator $generator) {}

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

        if (strtoupper((string) $payrollRun->country_code) !== 'SN') {
            return response()->json([
                'message' => 'La déclaration IPRES SN ne s\'applique qu\'aux runs de paie sénégalais (country_code=SN).',
            ], 422);
        }

        $csv = $this->generator->generate($payrollRun);
        $totals = $this->generator->totals($payrollRun);

        $filename = sprintf(
            'IPRES_SN_%s_%s.csv',
            $payrollRun->period_start->format('Y-m'),
            now()->format('Ymd')
        );

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            'ipres-sn-declaration.csv'
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'X-IPRES-Totals' => json_encode([
                'slips' => $totals['slips'],
                'assiette_t1' => $totals['assiette_t1'],
                't1_salariale' => $totals['t1_salariale'],
                't2_salariale' => $totals['t2_salariale'],
                'css_famille_patronale' => $totals['css_famille_patronale'],
            ]),
        ]);
    }
}
