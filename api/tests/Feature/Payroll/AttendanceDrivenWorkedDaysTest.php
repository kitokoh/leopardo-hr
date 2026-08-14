<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1816 (F-20) — actual_days_worked depuis les logs de présence réels
 * (AttendanceLog → PayrollCalculator::computeWorkedDays).
 *
 * Vérifie : jours distincts avec log valide, exclusion des logs annulés/
 * rejetés/incomplets, fallback prorata contrat sans présence, trace
 * has_attendance_data sur le bulletin.
 */
class AttendanceDrivenWorkedDaysTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_actual_days_worked_counts_distinct_valid_attendance_days(): void
    {
        [$company, $employee] = $this->actors();
        $run = $this->makeRun($company, '2026-05-01');

        // 3 jours distincts avec logs valides (le doublon même jour n'est pas
        // insérable : contrainte unique employee_id/date/session_number).
        $this->log($company, $employee, '2026-05-04', 'ontime');
        $this->log($company, $employee, '2026-05-05', 'late');
        $this->log($company, $employee, '2026-05-06', 'ontime');

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(3.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
        $this->assertSame(22.0, $worked['working_days']);
    }

    public function test_incomplete_logs_are_excluded(): void
    {
        [$company, $employee] = $this->actors();
        $run = $this->makeRun($company, '2026-05-01');

        // Filtre F-20 (#1816) : whereNotIn('status', ['cancelled', 'rejected',
        // 'incomplete']) — seuls ces statuts sont exclus du décompte.
        $this->log($company, $employee, '2026-05-04', 'incomplete'); // exclu
        $this->log($company, $employee, '2026-05-05', 'leave');      // décompté
        $this->log($company, $employee, '2026-05-06', 'holiday');    // décompté
        $this->log($company, $employee, '2026-05-07', 'ontime');     // décompté

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(3.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
    }

    public function test_falls_back_to_contract_prorata_without_attendance_data(): void
    {
        [$company, $employee] = $this->actors();
        $run = $this->makeRun($company, '2026-05-01');

        // Aucun log → prorata contrat plein mois = 22 jours, pas de données
        // de présence.
        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['actual_days_worked']);
        $this->assertFalse($worked['has_attendance_data']);
    }

    public function test_fallback_prorata_respects_mid_month_hire(): void
    {
        [$company, $employee] = $this->actors(['contract_start' => '2026-05-15']);
        $run = $this->makeRun($company, '2026-05-01');

        // Entrée le 15/05 : 17 jours calendaires sur 31 → prorata 22 × 17/31
        // = 12,06 jours (aucun log de présence).
        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(12.06, $worked['actual_days_worked']);
        $this->assertFalse($worked['has_attendance_data']);
    }

    public function test_attendance_days_are_scoped_to_the_run_period(): void
    {
        [$company, $employee] = $this->actors();
        $run = $this->makeRun($company, '2026-05-01');

        $this->log($company, $employee, '2026-04-30', 'ontime'); // hors période
        $this->log($company, $employee, '2026-05-02', 'ontime'); // dans la période

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(1.0, $worked['actual_days_worked']);
    }

    /**
     * @param  array<string, mixed>  $employeeOverrides
     * @return array{0: Company, 1: Employee}
     */
    private function actors(array $employeeOverrides = []): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $company->id,
            'status' => 'active',
            'contract_start' => '2025-01-10',
            'contract_type' => 'CDI',
        ], $employeeOverrides));

        return [$company, $employee];
    }

    private function makeRun(Company $company, string $periodStart): PayrollRun
    {
        return PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => $periodStart,
            'period_end' => Carbon::parse($periodStart)->endOfMonth()->toDateString(),
            'status' => 'draft',
        ]);
    }

    private function log(Company $company, Employee $employee, string $date, string $status = 'ontime'): AttendanceLog
    {
        return AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'check_in' => $date.' 08:00:00',
            'check_out' => $date.' 17:00:00',
            'status' => $status,
        ]);
    }
}
