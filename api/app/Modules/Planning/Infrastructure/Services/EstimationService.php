<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Contracts\Attendance\AttendanceLogReader;
use App\Shared\Contracts\Attendance\AttendanceLogView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estimations d'heures/gains à partir des journaux de pointage.
 *
 * #8254 (BOS-023 cycle 2) : les journaux sont lus via les contrats Shared
 * `AttendanceLogReader`/`AttendanceLogView` — le module Attendance n'est
 * plus importé directement (règle d'isolation #5584). Les appelants qui
 * passent des modèles `AttendanceLog` restent compatibles : le modèle
 * implémente `AttendanceLogView`.
 */
class EstimationService
{
    private const EXPECTED_HOURS_PER_DAY = 8.0;

    private const DEFAULT_WORKING_DAYS_PER_MONTH = 22;

    private const DEFAULT_OVERTIME_RATE_1 = 1.25;

    public function __construct(
        private readonly AttendanceLogReader $attendanceLogs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dailySummary(Employee $employee, ?string $date = null): array
    {
        $company = currentCompany();

        $dateLocal = $date
            ? (Carbon::createFromFormat('Y-m-d', $date, $company->timezone) ?? now()->setTimezone($company->timezone))->startOfDay()
            : now('UTC')->setTimezone($company->timezone)->startOfDay();

        $dateKey = $dateLocal->toDateString();

        $logs = $this->attendanceLogs->logsForEmployeeOnDate((int) $employee->id, $dateKey);

        return $this->dailySummaryFromLogs($employee, $logs, $dateKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function quickEstimate(Employee $employee, string $from, string $to): array
    {
        $company = currentCompany();

        $fromLocal = (Carbon::createFromFormat('Y-m-d', $from, $company->timezone) ?? now()->setTimezone($company->timezone))->startOfDay();
        $toLocal = (Carbon::createFromFormat('Y-m-d', $to, $company->timezone) ?? now()->setTimezone($company->timezone))->startOfDay();

        $logsByDate = collect($this->attendanceLogs->logsForEmployeeBetween(
            (int) $employee->id,
            $fromLocal->toDateString(),
            $toLocal->toDateString(),
        ))->groupBy(fn (AttendanceLogView $log) => $log->date()?->format('Y-m-d') ?? 'unknown-date');

        $workingDays = $this->countWorkingDaysInclusive($employee, $fromLocal, $toLocal);

        $daysPresent = 0;
        $totalHours = 0.0;
        $totalOvertime = 0.0;
        $gross = 0.0;
        $breakdown = [];

        foreach ($logsByDate as $dateKey => $logs) {
            $daySummary = $this->dailySummaryFromLogs($employee, $logs, (string) $dateKey);

            if ((int) $daySummary['sessions_count'] === 0) {
                continue;
            }

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

    /**
     * @return array<string, mixed>
     */
    public function dailySummaryFromLog(Employee $employee, ?AttendanceLogView $log, ?string $date = null): array
    {
        return $this->dailySummaryFromLogs(
            employee: $employee,
            logs: $log ? collect([$log]) : collect(),
            date: $date,
        );
    }

    /**
     * @param  iterable<int, AttendanceLogView>  $logs  journaux (modèles
     *                                                  `AttendanceLog` acceptés — ils implémentent `AttendanceLogView`)
     * @return array<string, mixed>
     */
    public function dailySummaryFromLogs(Employee $employee, iterable $logs, ?string $date = null): array
    {
        $company = currentCompany();
        $dateKey = $date ?: now('UTC')->setTimezone($company->timezone)->toDateString();

        $sessions = $logs instanceof Collection
            ? $logs->values()
            : collect($logs)->values();

        $sessions = $sessions
            ->filter(fn (AttendanceLogView $log) => $log->checkIn() !== null)
            ->sortBy(fn (AttendanceLogView $log) => (int) ($log->sessionNumber() ?? 1))
            ->values();

        if ($sessions->isEmpty()) {
            return [
                'employee_id' => $employee->id,
                'matricule' => $employee->matricule,
                'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'date' => $dateKey,
                'check_in' => null,
                'check_out' => null,
                'sessions_count' => 0,
                'hours_worked' => 0.0,
                'overtime_hours' => 0.0,
                'late_minutes' => 0,
                'base_gain' => 0.0,
                'overtime_gain' => 0.0,
                'total_estimated' => 0.0,
                'currency' => $company->currency,
                'status' => 'absent',
            ];
        }

        $nowUtc = now('UTC');
        /** @var AttendanceLogView $firstSession */
        $firstSession = $sessions->first();
        /** @var AttendanceLogView $lastSession */
        $lastSession = $sessions->last();
        $openSession = $sessions->first(fn (AttendanceLogView $log) => $log->checkOut() === null);

        $checkInUtc = $firstSession->checkIn();
        $checkOutUtc = $lastSession->checkOut();

        $status = $openSession
            ? 'incomplete'
            : ($sessions->contains(fn (AttendanceLogView $log) => $log->status() === 'late') ? 'late' : 'complete');

        $hoursWorked = round($sessions->sum(function (AttendanceLogView $log) use ($nowUtc): float {
            if ($log->hoursWorked() !== null) {
                return (float) $log->hoursWorked();
            }

            $checkIn = $log->checkIn();
            if ($checkIn === null) {
                return 0.0;
            }

            return round(($log->checkOut() ?? $nowUtc)->diffInMinutes($checkIn) / 60, 2);
        }), 2);

        $recordedOvertime = round($sessions->sum(fn (AttendanceLogView $log): float => $log->overtimeHours() ?? 0.0), 2);
        $thresholdOvertime = max(0.0, round($hoursWorked - self::EXPECTED_HOURS_PER_DAY, 2));
        $overtimeHours = max($recordedOvertime, $thresholdOvertime);
        $lateMinutes = (int) $sessions->sum(fn (AttendanceLogView $log): int => $log->lateMinutes() ?? 0);

        [$baseHourlyRate, $overtimeRate] = $this->resolveRates($employee);

        $baseHours = max(0.0, round($hoursWorked - $overtimeHours, 2));
        $baseGain = round($baseHours * $baseHourlyRate, 2);
        $overtimeGain = round($overtimeHours * $baseHourlyRate * $overtimeRate, 2);
        $total = round($baseGain + $overtimeGain, 2);

        return [
            'employee_id' => $employee->id,
            'matricule' => $employee->matricule,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'date' => $dateKey,
            'check_in' => $checkInUtc !== null
                ? $checkInUtc->copy()->setTimezone($company->timezone)->format('H:i')
                : null,
            'check_out' => $checkOutUtc !== null
                ? $checkOutUtc->copy()->setTimezone($company->timezone)->format('H:i')
                : null,
            'sessions_count' => $sessions->count(),
            'hours_worked' => $hoursWorked,
            'overtime_hours' => $overtimeHours,
            'late_minutes' => $lateMinutes,
            'base_gain' => $baseGain,
            'overtime_gain' => $overtimeGain,
            'total_estimated' => $total,
            'currency' => $company->currency,
            'status' => $status,
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
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
            // DEFAULT_WORKING_DAYS_PER_MONTH = 22 — garde superflue (PHPStan greater.alwaysTrue)
            $daily = $salaryBase / self::DEFAULT_WORKING_DAYS_PER_MONTH;
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

        if (! $this->hrModelTemplatesTableExists()) {
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

    private function hrModelTemplatesTableExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('hr_model_templates');
        }

        $table = DB::selectOne("select to_regclass('public.hr_model_templates') as table_name");

        return $table?->table_name !== null;
    }
}
