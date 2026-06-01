<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\SalaryAdvance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 61 - solde employe et cycle de paie mobile-first.
 */
class PayrollCycleService
{
    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function getCurrentCycle(Company $company): array
    {
        $settings = $this->payrollSettings($company);
        $now = Carbon::now($company->timezone ?: 'UTC');

        return match ($settings['pay_cycle']) {
            'daily' => $this->dailyPeriod($now),
            'weekly' => $this->weeklyPeriod($now, $settings['week_start']),
            default => $this->monthlyPeriod($now),
        };
    }

    /**
     * @return array{
     *     employee_id: int,
     *     employee_name: string,
     *     period: array{start: string, end: string, label: string, cycle: string},
     *     currency: string,
     *     gross_due: float,
     *     advances: float,
     *     paid: float,
     *     remaining: float,
     *     pay_slip: array{id: int|null, status: string|null, payroll_run_id: int|null}
     * }
     */
    public function getEmployeeBalance(Employee $employee): array
    {
        $company = $this->resolveCompany($employee);
        if (($company instanceof Company) === false) {
            throw new \RuntimeException('Employee company is required to calculate payroll balance.');
        }

        $cycle = $this->getCurrentCycle($company);
        $settings = $this->payrollSettings($company);

        /** @var PayrollRun|null $payrollRun */
        $payrollRun = PayrollRun::query()
            ->where('company_id', $employee->company_id)
            ->where('period_start', '<=', $cycle['end'])
            ->where('period_end', '>=', $cycle['start'])
            ->orderByDesc('period_start')
            ->first();

        $grossDue = $this->fallbackGrossDue($employee, $settings['pay_cycle']);
        $paid = 0.0;
        $paySlipPayload = [
            'id' => null,
            'status' => null,
            'payroll_run_id' => null,
        ];

        if ($payrollRun !== null) {
            /** @var PaySlip|null $paySlip */
            $paySlip = $payrollRun->paySlips()
                ->where('employee_id', $employee->id)
                ->first();

            if ($paySlip !== null) {
                $grossDue = (float) ($paySlip->net_salary ?? 0);
                $paid = $payrollRun->status === 'paid' ? $grossDue : 0.0;
                $paySlipPayload = [
                    'id' => $paySlip->id,
                    'status' => $paySlip->status,
                    'payroll_run_id' => $payrollRun->id,
                ];
            }
        }

        $advances = $this->cycleAdvances($employee, $cycle['start'], $cycle['end']);
        $remaining = max(0.0, $grossDue - $advances - $paid);

        return [
            'employee_id' => $employee->id,
            'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'period' => [
                'start' => $cycle['start']->toDateString(),
                'end' => $cycle['end']->toDateString(),
                'label' => $cycle['label'],
                'cycle' => $settings['pay_cycle'],
            ],
            'currency' => $company->currency,
            'gross_due' => round($grossDue, 2),
            'advances' => round($advances, 2),
            'paid' => round($paid, 2),
            'remaining' => round($remaining, 2),
            'pay_slip' => $paySlipPayload,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMobileSummary(Employee $actor, int $limit = 50): array
    {
        $isGlobalPayrollManager = $actor->hasManagerRole('principal', 'rh', 'comptable');
        $hasManagerColumn = Schema::hasColumn('employees', 'manager_id');

        $employees = Employee::query()
            ->select($this->employeeSummaryColumns())
            ->where('company_id', $actor->company_id)
            ->when(Schema::hasColumn('employees', 'status'), function ($query): void {
                $query->where('status', '!=', 'archived');
            })
            ->when($isGlobalPayrollManager === false && $hasManagerColumn, function ($query) use ($actor): void {
                $query->where(function ($scope) use ($actor): void {
                    $scope->where('id', $actor->id)
                        ->orWhere('manager_id', $actor->id);
                });
            })
            ->when($isGlobalPayrollManager === false && $hasManagerColumn === false, function ($query) use ($actor): void {
                $query->where('id', $actor->id);
            })
            ->when(Schema::hasColumn('employees', 'first_name'), fn ($query) => $query->orderBy('first_name'))
            ->when(Schema::hasColumn('employees', 'last_name'), fn ($query) => $query->orderBy('last_name'))
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return $employees
            ->map(fn (Employee $employee): array => $this->getEmployeeBalance($employee))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function employeeSummaryColumns(): array
    {
        $columns = [
            'id',
            'company_id',
            'first_name',
            'last_name',
            'matricule',
            'role',
            'manager_role',
            'manager_id',
            'salary_type',
            'salary_base',
            'hourly_rate',
            'status',
        ];

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn('employees', $column)
        ));
    }

    public function closeCycle(PayrollRun $run): PayrollRun
    {
        $run->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        /** @var PayrollRun $fresh */
        $fresh = $run->fresh();

        return $fresh;
    }

    /**
     * @return array{pay_cycle: string, pay_day: int, week_start: int}
     */
    private function payrollSettings(Company $company): array
    {
        $metadataPayroll = $company->metadata['payroll'] ?? [];
        $metadataPayroll = is_array($metadataPayroll) ? $metadataPayroll : [];

        $settings = [
            'pay_cycle' => (string) ($metadataPayroll['pay_cycle'] ?? $this->companySetting('pay_cycle', 'monthly')),
            'pay_day' => (int) ($metadataPayroll['pay_day'] ?? $this->companySetting('pay_day', '1')),
            'week_start' => (int) ($metadataPayroll['week_start'] ?? $this->companySetting('week_start', '1')),
        ];

        if (in_array($settings['pay_cycle'], ['daily', 'weekly', 'monthly'], true) === false) {
            $settings['pay_cycle'] = 'monthly';
        }

        $settings['pay_day'] = max(1, min($settings['pay_day'], 31));
        $settings['week_start'] = max(1, min($settings['week_start'], 7));

        return $settings;
    }

    private function resolveCompany(Employee $employee): ?Company
    {
        if (app()->bound('current_company')) {
            $company = currentCompany();
            if ((string) $company->id === (string) $employee->company_id) {
                return $company;
            }
        }

        $company = $employee->relationLoaded('company') ? $employee->company : null;
        if ($company instanceof Company) {
            return $company;
        }

        /** @var Company|null $company */
        $company = Company::query()->where('id', $employee->company_id)->first();

        return $company;
    }

    private function companySetting(string $key, string $default): string
    {
        if (Schema::hasTable('company_settings') === false) {
            return $default;
        }

        $value = CompanySetting::query()->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function fallbackGrossDue(Employee $employee, string $payCycle): float
    {
        $salaryType = $employee->salary_type ?? 'fixed';
        $salaryBase = (float) ($employee->salary_base ?? 0);
        $hourlyRate = (float) ($employee->hourly_rate ?? 0);

        if ($salaryType === 'hourly') {
            return match ($payCycle) {
                'daily' => $hourlyRate * 8,
                'weekly' => $hourlyRate * 40,
                default => $hourlyRate * 173.33,
            };
        }

        if ($salaryType === 'daily') {
            return match ($payCycle) {
                'daily' => $salaryBase,
                'weekly' => $salaryBase * 5,
                default => $salaryBase * 22,
            };
        }

        return match ($payCycle) {
            'daily' => $salaryBase / 22,
            'weekly' => $salaryBase / 4.345,
            default => $salaryBase,
        };
    }

    private function cycleAdvances(Employee $employee, Carbon $start, Carbon $end): float
    {
        $query = SalaryAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('company_id', $employee->company_id);

        if (Schema::hasColumn('salary_advances', 'validation_status')) {
            $query->whereIn('validation_status', ['payment_declared', 'employee_confirmed']);
        } else {
            $query->where('status', 'approved');
        }

        if (Schema::hasColumn('salary_advances', 'payment_declared_at')) {
            $query->whereBetween('payment_declared_at', [$start, $end]);
        } else {
            $query->whereBetween('updated_at', [$start, $end]);
        }

        return (float) $query->sum('amount');
    }

    /** @return array{start: Carbon, end: Carbon, label: string} */
    private function monthlyPeriod(Carbon $now): array
    {
        return [
            'start' => $now->copy()->startOfMonth(),
            'end' => $now->copy()->endOfMonth(),
            'label' => $now->format('Y-m'),
        ];
    }

    /** @return array{start: Carbon, end: Carbon, label: string} */
    private function weeklyPeriod(Carbon $now, int $weekStart = 1): array
    {
        $start = $now->copy()->startOfWeek($weekStart);

        return [
            'start' => $start,
            'end' => $start->copy()->addDays(6)->endOfDay(),
            'label' => $now->format('Y-\WW'),
        ];
    }

    /** @return array{start: Carbon, end: Carbon, label: string} */
    private function dailyPeriod(Carbon $now): array
    {
        return [
            'start' => $now->copy()->startOfDay(),
            'end' => $now->copy()->endOfDay(),
            'label' => $now->toDateString(),
        ];
    }
}
