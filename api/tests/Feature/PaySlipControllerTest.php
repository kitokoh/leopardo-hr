<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\PaySlipLine;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PaySlipControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_manager_can_list_pay_slips_for_own_payroll_run(): void
    {
        [$company, $manager, $employee] = $this->payrollActor();
        [$run, $slip] = $this->payrollSlip($company, $employee);
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $this->payrollSlip($otherCompany, $otherEmployee);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/pay-slips");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $slip->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_employee_self_service_lists_only_validated_or_sent_own_slips(): void
    {
        [$company, , $employee] = $this->payrollActor();
        [, $validated] = $this->payrollSlip($company, $employee, ['status' => 'validated']);
        $this->payrollSlip($company, $employee, ['status' => 'calculated']);
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $this->payrollSlip($company, $otherEmployee, ['status' => 'sent']);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/pay-slips');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $validated->id)
            ->assertJsonPath('data.0.status', 'validated');
    }

    public function test_employee_can_open_own_validated_pay_slip_but_not_other_tenant_slip(): void
    {
        [$company, , $employee] = $this->payrollActor();
        [, $ownSlip] = $this->payrollSlip($company, $employee, ['status' => 'sent']);
        [$otherCompany, , $otherEmployee] = $this->payrollActor();
        [, $otherSlip] = $this->payrollSlip($otherCompany, $otherEmployee, ['status' => 'sent']);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/me/pay-slips/{$ownSlip->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownSlip->id);

        $this->getJson("/api/v1/me/pay-slips/{$otherSlip->id}")
            ->assertNotFound();
    }

    public function test_manager_can_download_pdf_for_company_pay_slip(): void
    {
        [$company, $manager, $employee] = $this->payrollActor();
        [, $slip] = $this->payrollSlip($company, $employee, ['status' => 'validated']);

        Sanctum::actingAs($manager);

        $response = $this->get("/api/v1/pay-slips/{$slip->id}/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="bulletin_'.$employee->id.'_2026_05.pdf"');
    }

    public function test_send_slips_requires_validated_run_and_marks_emailable_slips_sent(): void
    {
        [$company, $manager, $employee] = $this->payrollActor();
        [$draftRun] = $this->payrollSlip($company, $employee, ['run_status' => 'calculated']);
        [$validatedRun, $slip] = $this->payrollSlip($company, $employee, [
            'run_status' => 'validated',
            'status' => 'validated',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$draftRun->id}/send-slips")
            ->assertStatus(422);

        $response = $this->postJson("/api/v1/payroll-runs/{$validatedRun->id}/send-slips");

        $response->assertOk()
            ->assertJsonPath('sent_count', 1)
            ->assertJsonPath('total_slips', 1);
        $this->assertSame('sent', $slip->fresh()->status);
    }

    public function test_employee_cannot_list_pay_slips_for_payroll_run(): void
    {
        [$company, , $employee] = $this->payrollActor();
        [$run] = $this->payrollSlip($company, $employee);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/pay-slips")
            ->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function payrollActor(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $manager, $employee];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function payrollSlip(Company $company, Employee $employee, array $overrides = []): array
    {
        $run = $overrides['run'] ?? PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => $overrides['country_code'] ?? 'DZ',
            'period_start' => $overrides['period_start'] ?? '2026-05-01',
            'period_end' => $overrides['period_end'] ?? '2026-05-31',
            'status' => $overrides['run_status'] ?? 'validated',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $overrides['gross_salary'] ?? 120000,
            'total_deductions' => $overrides['total_deductions'] ?? 22000,
            'net_salary' => $overrides['net_salary'] ?? 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => $overrides['status'] ?? 'validated',
        ]);

        PaySlipLine::query()->create([
            'pay_slip_id' => $slip->id,
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => 120000,
            'rate' => 1,
            'amount' => 120000,
            'order' => 1,
        ]);

        return [$run, $slip];
    }
}
