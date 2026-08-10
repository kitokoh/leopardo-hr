<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Exceptions\PayrollBalanceUnavailableException;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 61 - solde employe et cycle de paie mobile-first.
 */
class PayrollCycleService
{
    /**
     * PA2-PAY-011 — Returns the configurable pay cycle settings for a company
     * (daily/weekly/monthly, pay day, week start), as currently persisted in
     * the company's `metadata.payroll` bag. Read-only counterpart of
     * updatePayCycleSettings().
     *
     * @return array{pay_cycle: string, pay_day: int, week_start: int}
     */
    public function getPayCycleSettings(Company $company): array
    {
        return $this->payrollSettings($company);
    }

    /**
     * PA2-PAY-011 — Persists company-level pay cycle configuration
     * (daily/hebdomadaire/mensuel, pay day, week start) into the company's
     * `metadata.payroll` bag so subsequent getCurrentCycle()/getEmployeeBalance()
     * calls immediately reflect the new rule. Only the provided keys are
     * updated; omitted keys keep their previous value.
     *
     * @param  array{pay_cycle?: string, pay_day?: int, week_start?: int}  $changes
     * @return array{pay_cycle: string, pay_day: int, week_start: int}
     */
    public function updatePayCycleSettings(Company $company, array $changes): array
    {
        $current = $this->payrollSettings($company);

        $metadata = $company->metadata ?? [];
        $metadata = is_array($metadata) ? $metadata : [];
        $payroll = $metadata['payroll'] ?? [];
        $payroll = is_array($payroll) ? $payroll : [];

        if (array_key_exists('pay_cycle', $changes)) {
            $payCycle = $changes['pay_cycle'];
            $payroll['pay_cycle'] = in_array($payCycle, ['daily', 'weekly', 'monthly'], true)
                ? $payCycle
                : $current['pay_cycle'];
        }

        if (array_key_exists('pay_day', $changes)) {
            $payroll['pay_day'] = max(1, min((int) $changes['pay_day'], 31));
        }

        if (array_key_exists('week_start', $changes)) {
            $payroll['week_start'] = max(1, min((int) $changes['week_start'], 7));
        }

        $metadata['payroll'] = $payroll;

        $company->metadata = $metadata;
        $company->save();

        return $this->payrollSettings($company->fresh());
    }

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
     * PA2-PAY-003 — Lets a manager preview the effect of a candidate pay
     * cycle rule (journalier/hebdomadaire/mensuel, pay day, week start)
     * before actually saving it via updatePayCycleSettings(). Computes the
     * resulting period (start/end/label) and an estimated payroll total for
     * the company's active employees under that candidate rule, without
     * persisting anything or creating a PayrollRun.
     *
     * Unlike updatePayCycleSettings(), omitted keys fall back to the
     * company's *currently saved* settings rather than being left untouched
     * on a persisted record, since nothing is written here.
     *
     * @param  array{pay_cycle?: string, pay_day?: int, week_start?: int}  $overrides
     * @return array{
     *     settings: array{pay_cycle: string, pay_day: int, week_start: int},
     *     period: array{start: string, end: string, label: string},
     *     next_payment_date: string,
     *     currency: string,
     *     employee_count: int,
     *     estimated_total_gross: float,
     * }
     */
    public function previewCycle(Company $company, array $overrides = []): array
    {
        $settings = $this->candidateSettings($company, $overrides);
        $now = Carbon::now($company->timezone ?: 'UTC');

        $cycle = match ($settings['pay_cycle']) {
            'daily' => $this->dailyPeriod($now),
            'weekly' => $this->weeklyPeriod($now, $settings['week_start']),
            default => $this->monthlyPeriod($now),
        };

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->when($this->columnExists('employees', 'status'), function ($query): void {
                $query->where('status', '!=', 'archived');
            })
            ->get();

        $estimatedTotal = $employees->sum(
            fn (Employee $employee): float => $this->fallbackGrossDue($employee, $settings['pay_cycle'])
        );

        return [
            'settings' => $settings,
            'period' => [
                'start' => $cycle['start']->toDateString(),
                'end' => $cycle['end']->toDateString(),
                'label' => $cycle['label'],
            ],
            'next_payment_date' => $this->nextPaymentDate($company, $cycle, $settings)->toDateString(),
            'currency' => $company->currency,
            'employee_count' => $employees->count(),
            'estimated_total_gross' => round((float) $estimatedTotal, 2),
        ];
    }

    /**
     * @return array{
     *     employee_id: int,
     *     employee_name: string,
     *     country: string,
     *     period: array{start: string, end: string, label: string, cycle: string},
     *     currency: string,
     *     gross_due: float,
     *     advances: float,
     *     paid: float,
     *     remaining: float,
     *     overtime_hours: float,
     *     overtime_pay: float,
     *     next_payment_date: string,
     *     pay_slip: array{id: int|null, status: string|null, payroll_run_id: int|null, receipt_available: bool}
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
            'receipt_available' => false,
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
                    'receipt_available' => in_array($paySlip->status, ['validated', 'sent'], true),
                ];
            }
        }

        $advances = $this->cycleAdvances($employee, $cycle['start'], $cycle['end']);
        $remaining = max(0.0, $grossDue - $advances - $paid);
        $overtimeHours = $this->cycleOvertimeHours($employee, $cycle['start'], $cycle['end']);
        $overtimePay = $this->estimateOvertimePay($employee, $overtimeHours);

        return [
            'employee_id' => $employee->id,
            'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'country' => $company->country,
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
            'overtime_hours' => round($overtimeHours, 2),
            'overtime_pay' => round($overtimePay, 2),
            'next_payment_date' => $this->nextPaymentDate($company, $cycle, $settings)->toDateString(),
            'pay_slip' => $paySlipPayload,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMobileSummary(Employee $actor, int $limit = 50): array
    {
        $isGlobalPayrollManager = $actor->hasManagerRole('principal', 'rh', 'comptable');
        $hasManagerColumn = $this->columnExists('employees', 'manager_id');

        $employees = Employee::query()
            ->select($this->employeeSummaryColumns())
            ->where('company_id', $actor->company_id)
            ->when($this->columnExists('employees', 'status'), function ($query): void {
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
            ->when($this->columnExists('employees', 'first_name'), fn ($query) => $query->orderBy('first_name'))
            ->when($this->columnExists('employees', 'last_name'), fn ($query) => $query->orderBy('last_name'))
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return $employees
            ->map(fn (Employee $employee): array => $this->safeEmployeeBalance($employee))
            ->values()
            ->all();
    }

    /**
     * Solde employé avec erreur VISIBLE (S-3, #1663).
     *
     * L'ancien `safeEmployeeBalance()` avalait toute exception et renvoyait
     * un payload de secours à zéros (`warning: partial_balance_fallback`) —
     * le client mobile affichait un solde de 0 alors que le calcul avait
     * échoué (dette silencieuse). Désormais l'exception est journalisée puis
     * propagée : l'API répond 500 explicite, le client sait que le solde est
     * indisponible, et le rapport d'anomalies reste lisible (aucune valeur
     * inventée).
     *
     * @throws PayrollBalanceUnavailableException
     *
     * @return array<string, mixed>
     */
    private function safeEmployeeBalance(Employee $employee): array
    {
        try {
            return $this->getEmployeeBalance($employee);
        } catch (\Throwable $exception) {
            Log::error('payroll.mobile_summary.employee_balance_failed', [
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'error' => $exception->getMessage(),
            ]);

            throw new PayrollBalanceUnavailableException($exception->getMessage());
        }
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
            static fn (string $column): bool => self::columnExists('employees', $column)
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
     * PA2-PAY-003 — Same shape as payrollSettings(), but merges the given
     * overrides on top of the company's persisted settings for preview
     * purposes. Invalid override values are ignored in favour of the
     * persisted/default value, matching updatePayCycleSettings()'s validation.
     *
     * @param  array{pay_cycle?: string, pay_day?: int, week_start?: int}  $overrides
     * @return array{pay_cycle: string, pay_day: int, week_start: int}
     */
    private function candidateSettings(Company $company, array $overrides): array
    {
        $settings = $this->payrollSettings($company);

        if (array_key_exists('pay_cycle', $overrides)
            && in_array($overrides['pay_cycle'], ['daily', 'weekly', 'monthly'], true)) {
            $settings['pay_cycle'] = $overrides['pay_cycle'];
        }

        if (array_key_exists('pay_day', $overrides)) {
            $settings['pay_day'] = max(1, min((int) $overrides['pay_day'], 31));
        }

        if (array_key_exists('week_start', $overrides)) {
            $settings['week_start'] = max(1, min((int) $overrides['week_start'], 7));
        }

        return $settings;
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
        if (! $this->tableExists('company_settings')) {
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

        if ($this->columnExists('salary_advances', 'validation_status')) {
            $query->whereIn('validation_status', ['payment_declared', 'employee_confirmed']);
        } else {
            $query->where('status', 'approved');
        }

        if ($this->columnExists('salary_advances', 'payment_declared_at')) {
            $query->whereBetween('payment_declared_at', [$start, $end]);
        } else {
            $query->whereBetween('updated_at', [$start, $end]);
        }

        return (float) $query->sum('amount');
    }

    /**
     * PA2-PAY-010 — Sum of `attendance_logs.overtime_hours` already recorded
     * for this employee within the current pay cycle. Attendance is the
     * source of truth for overtime (computed per punch against the
     * employee's schedule threshold, see AttendanceService::checkOut()); the
     * payroll dashboard only aggregates it, it never recomputes overtime
     * itself.
     */
    private function cycleOvertimeHours(Employee $employee, Carbon $start, Carbon $end): float
    {
        // Audit passe 3 (#1606, bug B6) : les gardes Schema::hasTable
        // dépendantes du search_path sont interdites — `current_schema()` vaut
        // `shared_tenants` (premier du path) alors que les tables vivent dans
        // `public` sur le vrai schéma, ce qui court-circuitait le calcul en
        // retournant toujours 0. La table existe dans les deux schémas.
        return (float) AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('company_id', $employee->company_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('overtime_hours');
    }

    /**
     * PA2-PAY-010 — Estimated overtime pay for the current cycle, at 1.5x the
     * employee's estimated hourly rate (same +50% placeholder majoration used
     * by AttendanceMonthlyReportService::estimatedOvertimeAmount(); this is a
     * dashboard estimate for the manager, not the legally-validated overtime
     * premium tiers a country's CountryRulesInterface::overtimeRateTiers()
     * applies when a payroll run is actually calculated).
     */
    private function estimateOvertimePay(Employee $employee, float $overtimeHours): float
    {
        if ($overtimeHours <= 0) {
            return 0.0;
        }

        return round($overtimeHours * $this->estimatedHourlyRate($employee) * 1.5, 2);
    }

    private function estimatedHourlyRate(Employee $employee): float
    {
        $hourlyRate = (float) ($employee->hourly_rate ?? 0);
        if ($hourlyRate > 0) {
            return round($hourlyRate, 2);
        }

        $salaryBase = (float) ($employee->salary_base ?? 0);
        if ($salaryBase <= 0) {
            return 0.0;
        }

        return round($salaryBase / 173.33, 2);
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

    /**
     * Next date the employee should expect to receive their pay for the current cycle.
     *
     * For daily/weekly cycles, payment is expected right after the period ends.
     * For monthly cycles, payment is expected on the configured `pay_day` of the
     * period-end month (or the last day of that month if `pay_day` overflows it),
     * bumped forward a day at a time if that date has already passed.
     *
     * @param  array{start: Carbon, end: Carbon, label: string}  $cycle
     * @param  array{pay_cycle: string, pay_day: int, week_start: int}  $settings
     */
    private function nextPaymentDate(?Company $company, array $cycle, array $settings): Carbon
    {
        $now = Carbon::now($company?->timezone ?: 'UTC');

        if ($settings['pay_cycle'] !== 'monthly') {
            $candidate = $cycle['end']->copy()->startOfDay();

            return $candidate->isPast() ? $now->copy()->startOfDay() : $candidate;
        }

        $payDay = min($settings['pay_day'], $cycle['end']->daysInMonth);
        $candidate = $cycle['end']->copy()->startOfMonth()->addDays($payDay - 1)->startOfDay();

        if ($candidate->isPast()) {
            $nextMonth = $candidate->copy()->addMonthNoOverflow();
            $payDayNextMonth = min($settings['pay_day'], $nextMonth->daysInMonth);
            $candidate = $nextMonth->startOfMonth()->addDays($payDayNextMonth - 1)->startOfDay();
        }

        return $candidate;
    }


    /**
     * Vérifie l'existence d'une table indépendamment du search_path : les
     * tables métier vivent dans `public` (vraies migrations) alors que
     * `current_schema()` résout `shared_tenants` (premier du path) — une
     * garde naïve serait toujours fausse sur le vrai schéma (#1597/#1606).
     */
    private static function tableExists(string $table): bool
    {
        return Schema::hasTable('public.'.$table) || Schema::hasTable($table);
    }

    /**
     * Idem pour une colonne (cf. tableExists).
     */
    private static function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn('public.'.$table, $column) || Schema::hasColumn($table, $column);
    }
}
