<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\SocialDeclarationGenerator;
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

        $employees = DB::table('employees')
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->select(['id', 'first_name', 'last_name', 'national_id', 'date_of_birth'])
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

        $companyName = DB::table('companies')
            ->where('id', $actor->company_id)
            ->value('name') ?? 'N/A';

        $companyNis = DB::table('companies')
            ->where('id', $actor->company_id)
            ->value('tax_id') ?? '';

        $declarationRows = $employees->map(function ($emp) use ($payrollData) {
            $payroll = $payrollData->get($emp->id);

            return [
                'employee_id' => $emp->id,
                'num_ss' => $emp->national_id ?? '',
                'last_name' => $emp->last_name ?? '',
                'first_name' => $emp->first_name ?? '',
                'date_naissance' => $emp->date_of_birth ?? '',
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

        $employees = DB::table('employees')
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->select(['id', 'first_name', 'last_name', 'national_id'])
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

        $companyName = DB::table('companies')
            ->where('id', $actor->company_id)
            ->value('name') ?? 'N/A';

        $companyAffiliate = DB::table('companies')
            ->where('id', $actor->company_id)
            ->value('tax_id') ?? '';

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
}
