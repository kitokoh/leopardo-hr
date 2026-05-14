<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PaySlip;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollCalculator;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PayrollRunControllerTest extends TestCase
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

    private function bindFakePayrollCalculator(): void
    {
        $this->app->instance(PayrollCalculator::class, new class extends PayrollCalculator
        {
            public function calculateRun(PayrollRun $run): PayrollRun
            {
                $employee = Employee::query()
                    ->where('company_id', $run->company_id)
                    ->where('status', 'active')
                    ->firstOrFail();

                PaySlip::query()->create([
                    'payroll_run_id' => $run->id,
                    'company_id' => $run->company_id,
                    'employee_id' => $employee->id,
                    'period_start' => $run->period_start,
                    'period_end' => $run->period_end,
                    'gross_salary' => 100000,
                    'total_deductions' => 25000,
                    'net_salary' => 75000,
                    'employer_contributions' => 12000,
                    'total_cost' => 112000,
                    'working_days' => 22,
                    'actual_days_worked' => 22,
                    'overtime_hours' => 0,
                    'status' => 'calculated',
                ]);

                $run->update([
                    'status' => 'calculated',
                    'total_gross' => 100000,
                    'total_deductions' => 25000,
                    'total_net' => 75000,
                    'total_employer_cost' => 112000,
                    'employee_count' => 1,
                    'calculated_at' => now(),
                ]);

                return $run->refresh();
            }
        });
    }

    public function test_manager_can_list_payroll_runs(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_manager_can_create_payroll_run(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.country_code', 'DZ');
    }

    public function test_manager_can_view_payroll_run(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'FR',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}");
        $response->assertOk();
        $response->assertJsonPath('data.country_code', 'FR');
    }

    public function test_employee_cannot_create_payroll_run(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_payroll_run_summary(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
            'total_gross' => 500000,
            'total_deductions' => 100000,
            'total_net' => 400000,
            'employee_count' => 10,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/summary");
        $response->assertOk();
    }

    public function test_manager_can_calculate_payroll_run_with_calculator_contract(): void
    {
        $this->bindFakePayrollCalculator();
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'calculated');
        $response->assertJsonPath('data.pay_slips_count', 1);
        $this->assertDatabaseHas('pay_slips', [
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'status' => 'calculated',
        ]);
    }

    public function test_manager_can_validate_calculated_run_and_slips(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
        ]);
        PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 100000,
            'total_deductions' => 25000,
            'net_salary' => 75000,
            'employer_contributions' => 12000,
            'total_cost' => 112000,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'calculated',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/validate");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'validated');
        $this->assertDatabaseHas('pay_slips', [
            'payroll_run_id' => $run->id,
            'status' => 'validated',
        ]);
        $this->assertSame($manager->id, $run->fresh()->validated_by);
    }

    public function test_manager_can_cancel_draft_run_but_not_paid_run(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $draft = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);
        $paid = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'status' => 'paid',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$draft->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/v1/payroll-runs/{$paid->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_payroll_runs_are_isolated_by_tenant(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $ownRun = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);
        $otherRun = PayrollRun::create([
            'company_id' => $otherCompany->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/payroll-runs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRun->id);

        $this->getJson("/api/v1/payroll-runs/{$otherRun->id}")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$otherRun->id}/cancel")->assertNotFound();
    }
}
