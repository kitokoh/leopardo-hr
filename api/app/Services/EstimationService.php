<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstimationService
{
    private const EXPECTED_HOURS_PER_DAY = 8.0;
    private const DEFAULT_WORKING_DAYS_PER_MONTH = 22;
    private const DEFAULT_OVERTIME_RATE_1 = 1.25;

    public function dailySummary(Employee $employee, ?string $date = null): array
    {
        $company = app('current_company');

        $dateLocal = $date
            ? Carbon::createFromFormat('Y-m-d', $date, $company->timezone)->startOfDay()
            : now('UTC')->setTimezone($company->timezone)->startOfDay();

        $dateKey = $dateLocal->toDateString();

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $dateKey)
            ->where('session_number', 1)
            ->orderByDesc('id')
            ->first();

        return $this->dailySummaryFromLog($employee, $log, $dateKey);
    }

    public function quickEstimate(Employee $employee, string $from, string $to): array
    {
        $company = app('current_company');

        $fromLocal = Carbon::createFromFormat('Y-m-d', $from, $company->timezone)->startOfDay();
        $toLocal = Carbon::createFromFormat('Y-m-d', $to, $company->timezone)->startOfDay();

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', '>=', $fromLocal->toDateString())
            ->where('date', '<=', $toLocal->toDateString())
            ->where('session_number', 1)
            ->orderBy('date')
            ->get();

        $workingDays = $this->countWorkingDaysInclusive($employee, $fromLocal, $toLocal);

        $daysPresent = 0;
        $totalHours = 0.0;
        $totalOvertime = 0.0;
        $gross = 0.0;
        $breakdown = [];

        foreach ($logs as $log) {
            if (! $log->check_in || ! $log->check_out) {
                continue;
            }

            $daySummary = $this->dailySummaryFromLog($employee, $log, $log->date->format('Y-m-d'));
            $daysPresent++;
            $totalHours += (float) $daySummary['hours_worked'];
            $totalOvertime += (float) $daySummary['overtime_hours'];
            $gross += (float) $daySummary['total_estimated'];

            $breakdown[] = [
                'date' => $daySummary['date'],
                'hours' => $daySummary['hours_worked'],
                'overtime_hours' => $daySummary['overtime_hours'],
                'base_gain' => $daySummary['base_gain'],
                'overtime_gain' => $daySummary['overtime_gain'],
                'total' => $daySummary['total_estimated'],
            ];
        }

        $gross = round($gross, 2);
        $deductions = round($gross * $this->resolveEmployeeDeductionRate($company->country), 2);
        $net = round($gross - $deductions, 2);

        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'period' => [
                'from' => $fromLocal->toDateString(),
                'to' => $toLocal->toDateString(),
                'working_days' => $workingDays,
                'days_present' => $daysPresent,
                'days_absent' => max(0, $workingDays - $daysPresent),
            ],
            'totals' => [
                'hours' => round($totalHours, 2),
                'overtime_hours' => round($totalOvertime, 2),
                'gross' => $gross,
                'deductions' => $deductions,
                'net' => $net,
            ],
            'currency' => $company->currency,
            'breakdown' => $breakdown,
            'disclaimer' => 'Estimation non officielle - le bulletin de paie fait foi',
        ];
    }

    public function dailySummaryFromLog(Employee $employee, ?AttendanceLog $log, ?string $date = null): array
    {
        $company = app('current_company');
        $dateKey = $date ?: now('UTC')->setTimezone($company->timezone)->toDateString();

        if (! $log || ! $log->check_in) {
            return [
                'employee_id' => $employee->id,
                'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'date' => $dateKey,
                'check_in' => null,
                'check_out' => null,
                'hours_worked' => 0.0,
                'overtime_hours' => 0.0,
                'base_gain' => 0.0,
                'overtime_gain' => 0.0,
                'total_estimated' => 0.0,
                'currency' => $company->currency,
                'status' => 'absent',
            ];
        }

        $nowUtc = now('UTC');
        $checkInUtc = $log->check_in;
        $checkOutUtc = $log->check_out;

        $status = $checkOutUtc ? 'complete' : 'incomplete';

        $hoursWorked = $log->hours_worked !== null
            ? (float) $log->hours_worked
            : round(($checkOutUtc ?? $nowUtc)->diffInMinutes($checkInUtc) / 60, 2);

        $overtimeHours = $log->overtime_hours !== null
            ? (float) $log->overtime_hours
            : max(0.0, round($hoursWorked - self::EXPECTED_HOURS_PER_DAY, 2));

        [$baseHourlyRate, $overtimeRate] = $this->resolveRates($employee);

        $baseHours = min(self::EXPECTED_HOURS_PER_DAY, $hoursWorked);
        $baseGain = round($baseHours * $baseHourlyRate, 2);
        $overtimeGain = round($overtimeHours * $baseHourlyRate * $overtimeRate, 2);
        $total = round($baseGain + $overtimeGain, 2);

        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'date' => $dateKey,
            'check_in' => $checkInUtc->copy()->setTimezone($company->timezone)->format('H:i'),
            'check_out' => $checkOutUtc?->copy()->setTimezone($company->timezone)->format('H:i'),
            'hours_worked' => $hoursWorked,
            'overtime_hours' => $overtimeHours,
            'base_gain' => $baseGain,
            'overtime_gain' => $overtimeGain,
            'total_estimated' => $total,
            'currency' => $company->currency,
            'status' => $status,
        ];
    }

    private function resolveRates(Employee $employee): array
    {
        $salaryType = $employee->salary_type ?? 'fixed';
        $salaryBase = (float) ($employee->salary_base ?? 0);
        $hourlyRate = (float) ($employee->hourly_rate ?? 0);

        $baseHourlyRate = 0.0;
        if ($salaryType === 'hourly') {
            $baseHourlyRate = $hourlyRate;
        } elseif ($salaryType === 'daily') {
            $baseHourlyRate = $salaryBase / self::EXPECTED_HOURS_PER_DAY;
        } else {
            $daily = self::DEFAULT_WORKING_DAYS_PER_MONTH > 0
                ? ($salaryBase / self::DEFAULT_WORKING_DAYS_PER_MONTH)
                : 0.0;
            $baseHourlyRate = $daily / self::EXPECTED_HOURS_PER_DAY;
        }

        return [
            $baseHourlyRate,
            self::DEFAULT_OVERTIME_RATE_1,
        ];
    }

    private function countWorkingDaysInclusive(Employee $employee, Carbon $from, Carbon $to): int
    {
        $workDays = $employee->schedule?->work_days;
        if (! is_array($workDays) || $workDays === []) {
            return $this->countWeekdaysInclusive($from, $to);
        }

        $count = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dayNumber = match ($cursor->dayOfWeekIso) {
                7 => 0,
                default => $cursor->dayOfWeekIso,
            };

            if (in_array($dayNumber, $workDays, true)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    private function countWeekdaysInclusive(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    private function resolveEmployeeDeductionRate(string $countryCode): float
    {
        $defaultRate = $countryCode === 'DZ' ? 0.09 : 0.0;

        if (! Schema::hasTable('hr_model_templates')) {
            return $defaultRate;
        }

        $row = DB::table(DB::getDriverName() === 'pgsql' ? 'public.hr_model_templates' : 'hr_model_templates')
            ->where('country_code', $countryCode)
            ->first();

        if (! $row || empty($row->cotisations)) {
            return $defaultRate;
        }

        $cotisations = json_decode((string) $row->cotisations, true);

        return (float) ($cotisations['total_salarial'] ?? $defaultRate);
    }
}
