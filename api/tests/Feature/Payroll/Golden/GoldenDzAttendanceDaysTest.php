<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-20 (issue #1816) : `actual_days_worked` branché sur les
 * logs de présence réels (AttendanceLog) au lieu du seul prorata de contrat.
 *
 * Méthode : valeur en dur calculée à la main — référence
 * docs/payroll/DZ_COMPLIANCE.md §5.
 *  - 18 logs valides sur la période → actual_days_worked = 18.0 ;
 *  - 0 log → fallback prorata contrat (22.0 pour un mois complet) ;
 *  - statuts cancelled/rejected/incomplete exclus du décompte ;
 *  - logs d'un autre tenant jamais comptés (isolation tenant) ;
 *  - `has_attendance_data` persisté sur le bulletin via calculateRun().
 */
class GoldenDzAttendanceDaysTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeRun(Company $company): PayrollRun
    {
        return PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);
    }

    private function makeEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_start' => '2025-01-01',
            'contract_end' => null,
        ]);

        return $employee;
    }

    private function makeLog(
        Company $company,
        Employee $employee,
        string $date,
        string $status = 'ontime'
    ): AttendanceLog {
        /** @var AttendanceLog $log */
        $log = AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => $status,
            'check_in' => "{$date} 08:00:00",
            'check_out' => "{$date} 17:00:00",
            'session_number' => 1,
        ]);

        return $log;
    }

    /**
     * 18 logs valides → actual_days_worked = 18.0 (pas un prorata).
     * Calcul manuel : 18 jours distincts pointés (01/07 → 18/07), statut
     * valide → 18.0 jours réellement travaillés.
     */
    public function test_actual_days_from_18_attendance_logs(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);
        $run = $this->makeRun($company);

        foreach (range(1, 18) as $day) {
            $this->makeLog($company, $employee, sprintf('2026-07-%02d', $day));
        }

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['working_days']);
        $this->assertSame(18.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
    }

    /**
     * 0 log sur la période → fallback prorata contrat (mois complet → 22.0).
     * Calcul manuel : contrat du 2025-01-01 sans fin, période 01→31/07 → la
     * recoupe couvre tout le mois → 22 × 31/31 = 22.0 jours.
     */
    public function test_actual_days_fallback_no_logs(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);
        $run = $this->makeRun($company);

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['working_days']);
        $this->assertSame(22.0, $worked['actual_days_worked']);
        $this->assertFalse($worked['has_attendance_data']);
    }

    /**
     * Les statuts cancelled / rejected / incomplete sont exclus du décompte :
     * 20 logs dont 3 invalides → 17 jours comptés.
     */
    public function test_invalid_status_logs_are_excluded(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);
        $run = $this->makeRun($company);

        foreach (range(1, 20) as $day) {
            $date = sprintf('2026-07-%02d', $day);
            $status = match ($day) {
                18 => 'cancelled',
                19 => 'rejected',
                20 => 'incomplete',
                default => 'ontime',
            };
            $this->makeLog($company, $employee, $date, $status);
        }

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(17.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
    }

    /**
     * Isolation tenant : les logs d'un AUTRE tenant ne sont pas comptés —
     * 18 logs dans la société B, 0 dans la société A → fallback prorata.
     */
    public function test_cross_tenant_logs_are_not_counted(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $employeeA = $this->makeEmployee($companyA);
        $employeeB = $this->makeEmployee($companyB);
        $run = $this->makeRun($companyA);

        foreach (range(1, 18) as $day) {
            $this->makeLog($companyB, $employeeB, sprintf('2026-07-%02d', $day));
        }

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employeeA);

        $this->assertSame(22.0, $worked['actual_days_worked']);
        $this->assertFalse($worked['has_attendance_data']);
    }

    /**
     * Bout en bout : calculateRun() persiste `has_attendance_data = true` sur
     * le bulletin quand l'employé a pointé 18 jours sur la période.
     */
    public function test_calculate_run_stores_has_attendance_data_on_slip(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $run = $this->makeRun($company);

        foreach (range(1, 18) as $day) {
            $this->makeLog($company, $employee, sprintf('2026-07-%02d', $day));
        }

        (new PayrollCalculator)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();

        $this->assertSame(18.0, (float) $slip->actual_days_worked);
        $this->assertTrue($slip->has_attendance_data);
    }
}
