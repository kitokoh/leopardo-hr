<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Services\SocialDeclarationGenerator;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocialDeclarationController extends Controller
{
    public function generateCnasDz(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $employees = Employee::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->get();

        $quarterMonths = match ($validated['quarter']) {
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12],
        };

        $payrollData = DB::table('pay_slips')
            ->join('payroll_runs', 'pay_slips.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $actor->company_id)
            ->where('pay_slips.status', 'validated')
            ->whereYear('payroll_runs.period_start', $validated['year'])
            ->whereIn(DB::raw('EXTRACT(MONTH FROM payroll_runs.period_start)'), $quarterMonths)
            ->select([
                'pay_slips.employee_id',
                DB::raw('SUM(pay_slips.gross_salary) as total_gross'),
                DB::raw('COUNT(DISTINCT EXTRACT(MONTH FROM payroll_runs.period_start)) as months_worked'),
            ])
            ->groupBy('pay_slips.employee_id')
            ->get()
            ->keyBy('employee_id');

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companyNis = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData) {
            $payroll = $payrollData->get($emp->id);

            return [
                'employee_id' => $emp->id,
                'num_ss' => $emp->national_id ?? '',
                'last_name' => $emp->last_name ?? '',
                'first_name' => $emp->first_name ?? '',
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

    public function generateCnssMa(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $employees = Employee::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->get();

        $quarterMonths = match ($validated['quarter']) {
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12],
        };

        $payrollData = DB::table('pay_slips')
            ->join('payroll_runs', 'pay_slips.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $actor->company_id)
            ->where('pay_slips.status', 'validated')
            ->whereYear('payroll_runs.period_start', $validated['year'])
            ->whereIn(DB::raw('EXTRACT(MONTH FROM payroll_runs.period_start)'), $quarterMonths)
            ->select([
                'pay_slips.employee_id',
                DB::raw('SUM(pay_slips.gross_salary) as total_gross'),
            ])
            ->groupBy('pay_slips.employee_id')
            ->get()
            ->keyBy('employee_id');

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

            return [
                'employee_id' => $emp->id,
                'num_cnss' => $emp->national_id ?? '',
                'last_name' => $emp->last_name ?? '',
                'first_name' => $emp->first_name ?? '',
                'cin' => '',
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'days_worked' => (int) ($attendance->days_worked ?? 0),
            ];
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

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $employees = Employee::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->get();

        $payrollData = DB::table('pay_slips')
            ->join('payroll_runs', 'pay_slips.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $actor->company_id)
            ->where('pay_slips.status', 'validated')
            ->whereYear('payroll_runs.period_start', $validated['year'])
            ->whereMonth('payroll_runs.period_start', $validated['month'])
            ->select([
                'pay_slips.employee_id',
                DB::raw('SUM(pay_slips.gross_salary) as total_gross'),
                DB::raw('SUM(pay_slips.net_salary) as total_net'),
            ])
            ->groupBy('pay_slips.employee_id')
            ->get()
            ->keyBy('employee_id');

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companySiret = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData) {
            $payroll = $payrollData->get($emp->id);

            return [
                'employee_id' => $emp->id,
                'nir' => $emp->national_id ?? '',
                'last_name' => $emp->last_name ?? '',
                'first_name' => $emp->first_name ?? '',
                'date_naissance' => $this->dateValue($emp->date_of_birth ?? null),
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'net_salary' => (float) ($payroll->total_net ?? 0),
                'net_imposable' => (float) ($payroll->total_net ?? 0),
                'hours_worked' => 151.67,
                'contract_type' => $emp->contract_type ?? 'CDI',
                'start_date' => $this->dateValue($emp->contract_start ?? null),
            ];
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
