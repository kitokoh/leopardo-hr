<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Support\Carbon;

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

        $workingDays = $this->countWeekdaysInclusive($fromLocal, $toLocal);

        $daysPresent = 0;
        $totalHours = 0.0;
        $totalOvertime = 0.0;
        $gross = 0.0;
        $breakdown = [];

        foreach ($logs as $log) {
            if (! $log->check_in || ! $log->check_out) {
                continue;
            }

            $daySummary = $this->dailySummary($employee, $log->date->format('Y-m-d'));
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

        $deductions = 0.0;
        if ($company->country === 'DZ') {
            $deductions = round($gross * 0.09, 2);
        }

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
            'disclaimer' => 'Estimation non officielle — le bulletin de paie fait foi',
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
}

