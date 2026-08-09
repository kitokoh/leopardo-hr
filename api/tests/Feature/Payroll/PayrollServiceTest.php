<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\PayrollValidated;
use App\Exceptions\PayrollAlreadyValidatedException;
use App\Exceptions\PayrollPeriodConflictException;
use App\Modules\Payroll\Domain\Models\Payroll;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\PayrollService;
use Illuminate\Support\Facades\Event;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-14 (#1602) : couverture de `PayrollService` (legacy
 * `payrolls`), seule classe majeure du module Payroll sans aucun test direct.
 */
class PayrollServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    public function test_create_computes_net_from_all_components(): void
    {
        $payroll = (new PayrollService())->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'overtime_amount' => 5000,
            'bonuses' => [['label' => 'Prime', 'amount' => 2000]],
            'deductions' => [['label' => 'Retenue', 'amount' => 1000]],
            'cotisations' => [['label' => 'CNAS', 'amount' => 5400]],
            'ir_amount' => 7042,
            'advance_deduction' => 3000,
            'absence_deduction' => 0,
            'penalty_deduction' => 0,
        ]);

        // net = 60000 + 5000 + 2000 − 1000 − 5400 − 7042 − 3000 = 50558
        $this->assertSame('draft', $payroll->status);
        $this->assertSame(50558.0, $payroll->net_salary);
        $this->assertSame(7, $payroll->period_month);
        $this->assertSame(2026, $payroll->period_year);
        $this->assertSame($this->company->id, $payroll->company_id);
    }

    public function test_create_rejects_duplicate_period(): void
    {
        $service = new PayrollService();
        $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
        ]);

        $this->expectException(PayrollPeriodConflictException::class);
        $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 70000,
        ]);
    }

    public function test_update_recomputes_net(): void
    {
        $service = new PayrollService();
        $payroll = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'ir_amount' => 7000,
        ]);
        $this->assertSame(53000.0, $payroll->net_salary);

        $updated = $service->update($payroll, ['gross_salary' => 80000, 'ir_amount' => 9000]);
        $this->assertSame(71000.0, $updated->net_salary);
    }

    public function test_update_rejects_validated_payroll(): void
    {
        $service = new PayrollService();
        $payroll = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
        ]);
        $service->validate($payroll, $this->manager);

        $this->expectException(PayrollAlreadyValidatedException::class);
        $service->update($payroll->refresh(), ['gross_salary' => 80000]);
    }

    public function test_validate_marks_validated_and_dispatches_event(): void
    {
        Event::fake([PayrollValidated::class]);

        $service = new PayrollService();
        $payroll = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
        ]);

        $validated = $service->validate($payroll, $this->manager);

        $this->assertSame('validated', $validated->status);
        $this->assertSame($this->manager->id, $validated->validated_by);
        $this->assertNotNull($validated->validated_at);

        Event::assertDispatched(PayrollValidated::class, fn ($event) => $event->payroll->id === $payroll->id);
    }

    public function test_validate_applies_advance_deduction(): void
    {
        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'amount' => 10000,
            'status' => 'active',
            'amount_remaining' => 6000,
            'monthly_deduction' => 2500,
            'validation_status' => 'employee_confirmed',
        ]);

        $service = new PayrollService();
        $payroll = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'advance_deduction' => 6000,
        ]);

        $service->validate($payroll, $this->manager);

        // L'avance de 6 000 restants est entièrement remboursée.
        $advance->refresh();
        $this->assertSame(0.0, $advance->amount_remaining);
        $this->assertSame('repaid', $advance->status);
    }

    public function test_validate_partially_reduces_advance_remaining(): void
    {
        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'amount' => 10000,
            'status' => 'active',
            'amount_remaining' => 6000,
            'monthly_deduction' => 2500,
            'validation_status' => 'employee_confirmed',
        ]);

        $service = new PayrollService();
        $payroll = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
            'advance_deduction' => 2500,
        ]);

        $service->validate($payroll, $this->manager);

        $advance->refresh();
        $this->assertSame(3500.0, $advance->amount_remaining);
        $this->assertSame('active', $advance->status);
    }

    public function test_delete_removes_draft_and_rejects_validated(): void
    {
        $service = new PayrollService();
        $payroll = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 60000,
        ]);

        $id = $payroll->id;
        $service->delete($payroll);
        $this->assertDatabaseMissing('payrolls', ['id' => $id]);

        $payroll2 = $service->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 8,
            'period_year' => 2026,
            'gross_salary' => 60000,
        ]);
        $service->validate($payroll2, $this->manager);

        $this->expectException(PayrollAlreadyValidatedException::class);
        $service->delete($payroll2->refresh());
    }

    public function test_net_never_negative(): void
    {
        $payroll = (new PayrollService())->create($this->manager, [
            'employee_id' => $this->employee->id,
            'period_month' => 7,
            'period_year' => 2026,
            'gross_salary' => 10000,
            'deductions' => [['label' => 'Retenue', 'amount' => 50000]],
            'ir_amount' => 5000,
        ]);

        $this->assertSame(0.0, $payroll->net_salary);
    }
}
