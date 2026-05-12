<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
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
}
