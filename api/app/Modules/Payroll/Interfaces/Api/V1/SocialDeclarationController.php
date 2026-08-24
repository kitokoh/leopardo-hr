<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Application\Services\SocialDeclarationService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CedeaoCnsDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CemacCnpsDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CnpsDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CnssDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\DasDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\IpresDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationGenerator;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SocialDeclarationController extends Controller
{
    public function __construct(
        private readonly DataAccessAuditLogger $auditLogger,
        private readonly SocialDeclarationService $declarationService,
    ) {}

    public function generateCnasDz(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.cnas_declaration');

        $validated = $request->validate([
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.cnas_declaration');

        $employees = $this->declarationService->activeEmployees((string) $actor->company_id);

        $quarterMonths = $this->declarationService->quarterMonths((string) $validated['quarter']);

        $payrollData = $this->declarationService->quarterPayrollData(
            (string) $actor->company_id,
            (int) $validated['year'],
            $quarterMonths,
            withMonthsCount: true,
        );

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companyNis = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData) {
            $payroll = $payrollData->get($emp->id);

            return [
                'employee_id' => (int) $emp->id,
                'num_ss' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'date_naissance' => $this->dateValue($emp->date_of_birth ?? null),
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'months_worked' => (int) ($payroll->months_worked ?? 0),
            ];
        })->filter(fn (array $row) => $row['gross_salary'] > 0);

        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateCnasDz(
            $companyName,
            $companyNis,
            $validated['quarter'],
            (int) $validated['year'],
            $declarationRows->values(),
        );

        return response()->json([
            'data' => [
                'format' => 'cnas_dz',
                'quarter' => $validated['quarter'],
                'year' => $validated['year'],
                'employee_count' => $declarationRows->count(),
                'content' => $content,
                'filename' => sprintf('CNAS_DZ_%s_%d_%s.txt', $validated['quarter'], $validated['year'], now()->format('Ymd')),
            ],
        ]);
    }

    /**
     * #5243 — Déclaration Annuelle des Salaires (DAS) DZ : CSV annuel agrégé
     * depuis les bulletins validés des runs DZ de l'année (une ligne par
     * employé : NIS, nom, mois, brut, CNAS 9 %/26 %, IRG, net + TOTAUX).
     * Manager principal/comptable, audit `payroll.das_declaration`.
     */
    public function generateDasDz(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.das_declaration');

        $year = (int) $validated['year'];

        $slips = PaySlip::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'validated')
            ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->whereHas('payrollRun', fn ($query) => $query->where('country_code', 'DZ'))
            ->with(['employee', 'lines'])
            ->get();

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company->name ?? 'N/A';
        $companyNis = $this->companyRegistrationNumber($company);

        $content = (new DasDeclarationGenerator)->generate(
            $companyName,
            $companyNis,
            $year,
            $slips,
        );

        return response()->json([
            'data' => [
                'format' => 'das_dz',
                'year' => $year,
                'employee_count' => $slips->groupBy('employee_id')->count(),
                'content' => $content,
                'filename' => sprintf('DAS_DZ_%d_%s.txt', $year, now()->format('Ymd')),
            ],
        ]);
    }

    public function generateCnssMa(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.cnss_declaration');

        $validated = $request->validate([
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.cnss_declaration');

        $employees = $this->declarationService->activeEmployees((string) $actor->company_id);

        $quarterMonths = $this->declarationService->quarterMonths((string) $validated['quarter']);

        $payrollData = $this->declarationService->quarterPayrollData(
            (string) $actor->company_id,
            (int) $validated['year'],
            $quarterMonths,
        );

        $attendanceData = DB::table('attendance_logs')
            ->where('company_id', $actor->company_id)
            ->whereYear('check_in', $validated['year'])
            ->whereIn(DB::raw('EXTRACT(MONTH FROM check_in)'), $quarterMonths)
            ->select([
                'employee_id',
                DB::raw('COUNT(DISTINCT DATE(check_in)) as days_worked'),
            ])
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companyAffiliate = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData, $attendanceData) {
            $payroll = $payrollData->get($emp->id);
            $attendance = $attendanceData->get($emp->id);

            /** @var array{employee_id: int, num_cnss: string, last_name: string, first_name: string, cin: string, gross_salary: float, days_worked: int} $row */
            $row = [
                'employee_id' => (int) $emp->id,
                'num_cnss' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'cin' => '',
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'days_worked' => (int) ($attendance->days_worked ?? 0),
            ];

            return $row;
        })->filter(fn (array $row) => $row['gross_salary'] > 0);

        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateCnssMa(
            $companyName,
            $companyAffiliate,
            $validated['quarter'],
            (int) $validated['year'],
            $declarationRows->values(),
        );

        return response()->json([
            'data' => [
                'format' => 'cnss_ma',
                'quarter' => $validated['quarter'],
                'year' => $validated['year'],
                'employee_count' => $declarationRows->count(),
                'content' => $content,
                'filename' => sprintf('CNSS_MA_%s_%d_%s.txt', $validated['quarter'], $validated['year'], now()->format('Ymd')),
            ],
        ]);
    }

    public function generateDsnFr(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.dsn_declaration');

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.dsn_declaration');

        $employees = $this->declarationService->activeEmployees((string) $actor->company_id);

        $payrollData = $this->declarationService->monthPayrollData(
            (string) $actor->company_id,
            (int) $validated['year'],
            (int) $validated['month'],
        );

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companySiret = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData) {
            $payroll = $payrollData->get($emp->id);

            /** @var array{employee_id: int, nir: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, net_salary: float, net_imposable?: float, hours_worked?: float, contract_type: string, start_date: string} $row */
            $row = [
                'employee_id' => (int) $emp->id,
                'nir' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'date_naissance' => $this->dateValue($emp->date_of_birth ?? null),
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'net_salary' => (float) ($payroll->total_net ?? 0),
                'net_imposable' => (float) ($payroll->total_net ?? 0),
                'hours_worked' => 151.67,
                'contract_type' => (string) ($emp->contract_type ?? 'CDI'),
                'start_date' => $this->dateValue($emp->contract_start ?? null),
            ];

            return $row;
        })->filter(fn (array $row) => $row['gross_salary'] > 0);

        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateDsnFr(
            $companyName,
            $companySiret,
            str_pad((string) $validated['month'], 2, '0', STR_PAD_LEFT),
            (int) $validated['year'],
            $declarationRows->values(),
        );

        return response()->json([
            'data' => [
                'format' => 'dsn_fr',
                'month' => $validated['month'],
                'year' => $validated['year'],
                'employee_count' => $declarationRows->count(),
                'content' => $content,
                'filename' => sprintf('DSN_FR_%02d_%d_%s.dsn', $validated['month'], $validated['year'], now()->format('Ymd')),
            ],
        ]);
    }

    /**
     * CEDEAO (#1830) — déclaration CNSS mensuelle Côte d'Ivoire (CSV).
     * 422 si le run n'est pas un run CI.
     */
    public function generateCnssCiDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'CI',
            'payroll.cnss_ci_declaration',
            "la Côte d'Ivoire (CNSS CI)",
        );

        $generator = new CnssDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('CNSS_CI_DAS_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * CEDEAO (#1830) — déclaration IPRES/CSS mensuelle Sénégal (CSV).
     * 422 si le run n'est pas un run SN.
     */
    public function generateIpresSnDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'SN',
            'payroll.ipres_sn_declaration',
            'le Sénégal (IPRES/CSS)',
        );

        $generator = new IpresDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('IPRES_SN_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * CEMAC/CM (#1823) — déclaration CNPS mensuelle Cameroun (format DAS) :
     * CSV téléchargeable, une ligne par bulletin validé du run + totaux.
     */
    public function generateCnpsCmDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'CM',
            'payroll.cnps_cm_declaration',
            'le Cameroun (CNPS CM)',
        );

        $generator = new CnpsDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('CNPS_CM_DAS_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * CEMAC (#2155) — déclaration CNSS mensuelle Gabon (GA, CSV) :
     * mêmes règles CNSS CEMAC que CM (retraite 2,5 %/5 %, famille 8 %,
     * AT 3 % — plafond 3 000 000 XAF), sans centimes additionnels.
     */
    public function generateCnssGaDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'GA',
            'payroll.cnss_ga_declaration',
            'le Gabon (CNSS GA)',
        );

        $generator = new CemacCnpsDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('CNSS_GA_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * CEMAC (#2155) — déclaration CNSS mensuelle Congo (CG, CSV) :
     * retraite 4 %/8 %, famille 10 %, AT 3 % — plafond 2 500 000 XAF.
     */
    public function generateCnssCgDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'CG',
            'payroll.cnss_cg_declaration',
            'le Congo (CNSS CG)',
        );

        $generator = new CemacCnpsDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('CNSS_CG_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * CEDEAO (#2158) — déclaration CNSS mensuelle Burkina Faso (CSV).
     * 422 si le run n'est pas un run BF.
     */
    public function generateCnssBfDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'BF',
            'payroll.cnss_bf_declaration',
            'le Burkina Faso (CNSS BF)',
        );

        $generator = new CedeaoCnsDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('CNSS_BF_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * CEDEAO (#2158) — déclaration INPS mensuelle Mali (CSV).
     * 422 si le run n'est pas un run ML.
     */
    public function generateInpsMlDeclaration(Request $request, PayrollRun $payrollRun): Response
    {
        $this->assertPayrollRunAccess(
            $request,
            $payrollRun,
            'ML',
            'payroll.inps_ml_declaration',
            'le Mali (INPS ML)',
        );

        $generator = new CedeaoCnsDeclarationGenerator;
        $content = $generator->generate($payrollRun);

        $filename = sprintf('INPS_ML_%d_%s.csv', $payrollRun->id, now()->format('Ymd'));

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * Gardes communes des déclarations par run : isolation tenant (404),
     * RBAC (403 — isManager() ou rôles précis) et garde pays (422), puis
     * journalisation d'audit. Retourne l'acteur authentifié (issue #3149).
     *
     * @param  list<string>|null  $requiredRoles
     */
    private function assertPayrollRunAccess(Request $request, PayrollRun $payrollRun, string $countryCode, string $auditKey, string $countryLabel, ?array $requiredRoles = null): Employee
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($requiredRoles === null) {
            if (! $actor->isManager()) {
                abort(403);
            }
        } elseif (! $actor->hasManagerRole(...$requiredRoles)) {
            abort(403);
        }

        if ($payrollRun->country_code !== $countryCode) {
            return response()->json(['message' => __('errors.PAYROLL_RUN_NOT_FOR_COUNTRY', ['country' => $countryLabel])], 422)->throwResponse();
        }

        $this->auditLogger->recordSensitive($request, $actor, $auditKey);

        return $actor;
    }

    private function companyRegistrationNumber(?Company $company): string
    {
        if ($company === null) {
            return '';
        }

        $metadata = $company->metadata ?? [];

        return (string) (
            $metadata['tax_id']
            ?? $metadata['nis']
            ?? $metadata['affiliate_number']
            ?? $metadata['siret']
            ?? ''
        );
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? '' : (string) $value;
    }
}
