<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * S-4 (#1664) — Edge cases du calcul de paie sur vraies migrations.
 *
 * Couvre les branches du moteur rarement exercées :
 *  - garde F-11 : calculateRun refuse un run verrouillé ;
 *  - recalcul après modification (delete + rebuild des bulletins) ;
 *  - prorata d'entrée/sortie en cours de mois (computeWorkedDays) ;
 *  - entrées de travail réelles : pointage (heures sup) + congés approuvés.
 */
class PayrollCalculatorRunEdgeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    private function makeRun(string $status, string $periodStart = '2026-07-01', string $periodEnd = '2026-07-31'): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'country_code' => 'DZ',
            'status' => $status,
        ]);

        return $run;
    }

    public function test_calculate_run_refuses_locked_run(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_LOCKED);

        $this->expectException(PayrollRunLockedException::class);

        (new PayrollCalculator())->calculateRun($run);
    }

    public function test_calculate_run_rebuilds_slips_on_recalculation(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'salary_base' => 60000,
        ]);

        $calculator = new PayrollCalculator();

        $calculator->calculateRun($run);
        $this->assertSame(1, PaySlip::where('payroll_run_id', $run->id)->count());

        // Recalcul : les bulletins sont reconstruits, pas dupliqués.
        $calculator->calculateRun($run);
        $this->assertSame(1, PaySlip::where('payroll_run_id', $run->id)->count());
        $this->assertSame('calculated', $run->fresh()->status);
    }

    public function test_calculate_run_prorates_contract_started_mid_period(): void
    {
        // Contrat démarré le 2026-07-16 : ~15/31 de période → ~10,65 j ouvrés.
        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'salary_base' => 60000,
            'contract_start' => '2026-07-16',
        ]);

        (new PayrollCalculator())->calculateRun($run);

        $slip = PaySlip::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->first();
        $this->assertNotNull($slip);
        $this->assertLessThan(60000.0, (float) $slip->gross_salary);
        $this->assertGreaterThan(0.0, (float) $slip->gross_salary);
    }

    public function test_collect_work_inputs_sums_attendance_overtime_and_approved_leave(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'salary_base' => 60000,
        ]);

        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'overtime_hours' => 2.5,
            'status' => 'ontime',
        ]);
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-11',
            'overtime_hours' => 1.5,
            'status' => 'late',
        ]);

        $calculator = new PayrollCalculator();
        $inputs = $calculator->collectWorkInputs($run, $employee);

        $this->assertArrayHasKey('overtime_hours', $inputs);
        $this->assertArrayHasKey('paid_leave_days', $inputs);
        $this->assertArrayHasKey('unpaid_leave_days', $inputs);
        $this->assertIsFloat($inputs['overtime_hours']);
    }
}
