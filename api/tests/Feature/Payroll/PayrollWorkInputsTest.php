<?php

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-20 : entrées de travail réelles (pointage + congés)
 * alimentant le calcul de paie (PayrollCalculator::collectWorkInputs).
 */
class PayrollWorkInputsTest extends TestCase
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

    public function test_overtime_is_summed_and_invalid_logs_excluded(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company);

        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-10', 'overtime_hours' => 2.5, 'status' => 'ontime']);
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-12', 'overtime_hours' => 1.5, 'status' => 'late']);
        // Log invalide (statut réel 'incomplete') : exclu.
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-14', 'overtime_hours' => 3.0, 'status' => 'incomplete']);
        // Hors période : exclu.
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-08-01', 'overtime_hours' => 9.0, 'status' => 'ontime']);

        $inputs = (new PayrollCalculator)->collectWorkInputs($run, $employee);

        $this->assertSame(4.0, $inputs['overtime_hours']);
    }

    public function test_paid_and_unpaid_leave_are_separated(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company);

        $paidType = AbsenceType::create(['company_id' => $company->id, 'name' => 'Congé payé', 'code' => 'PAID', 'is_paid' => true]);
        $unpaidType = AbsenceType::create(['company_id' => $company->id, 'name' => 'Congé sans solde', 'code' => 'UNPAID', 'is_paid' => false]);

        Absence::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'absence_type_id' => $paidType->id, 'start_date' => '2026-07-06', 'end_date' => '2026-07-10', 'days_count' => 5, 'status' => 'approved']);
        Absence::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'absence_type_id' => $unpaidType->id, 'start_date' => '2026-07-20', 'end_date' => '2026-07-22', 'days_count' => 3, 'status' => 'approved']);
        // En attente : exclu.
        Absence::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'absence_type_id' => $paidType->id, 'start_date' => '2026-07-27', 'end_date' => '2026-07-29', 'days_count' => 3, 'status' => 'pending']);

        $inputs = (new PayrollCalculator)->collectWorkInputs($run, $employee);

        $this->assertSame(5.0, $inputs['paid_leave_days']);
        $this->assertSame(3.0, $inputs['unpaid_leave_days']);
    }

    public function test_empty_period_returns_zeros(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company);

        $inputs = (new PayrollCalculator)->collectWorkInputs($run, $employee);

        $this->assertSame(0.0, $inputs['overtime_hours']);
        $this->assertSame(0.0, $inputs['paid_leave_days']);
        $this->assertSame(0.0, $inputs['unpaid_leave_days']);
    }

    // ── F-20 (#1816) : computeWorkedDays branché sur les AttendanceLog ─────

    public function test_compute_worked_days_from_attendance_logs_db(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company);

        // 5 jours distincts pointés (dont une double session le 07-07).
        foreach (['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10'] as $date) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => 'ontime',
                'overtime_hours' => 0,
            ]);
        }
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-07',
            'session_number' => 2,
            'status' => 'ontime',
            'overtime_hours' => 0,
        ]);

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(5.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
    }

    public function test_compute_worked_days_excludes_invalid_statuses(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company);

        // 3 jours valides (ontime × 2, late × 1).
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-06', 'status' => 'ontime']);
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-07', 'status' => 'late']);
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-08', 'status' => 'ontime']);

        // Statuts n'attestant pas une présence réelle : exclus du décompte.
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-09', 'status' => 'incomplete']);
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-10', 'status' => 'absent']);
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-11', 'status' => 'holiday']);
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-12', 'status' => 'leave']);

        // Hors période : exclu.
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-08-01', 'status' => 'ontime']);

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(3.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
    }

    public function test_compute_worked_days_only_valid_logs_falls_back_to_prorata(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = $this->makeRun($company);

        // Uniquement des logs invalides → aucun jour attesté → fallback prorata.
        AttendanceLog::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-07-06', 'status' => 'incomplete']);

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['actual_days_worked']);
        $this->assertFalse($worked['has_attendance_data']);
    }
}
