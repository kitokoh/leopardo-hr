<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class HrReportControllerTest extends TestCase
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

    private function actingAsManager(): Employee
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        return $manager;
    }

    private function actingAsEmployee(): Employee
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_headcount_returns_data_for_manager(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/headcount');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total', 'by_department', 'by_contract_type', 'by_gender'],
            ]);
    }

    public function test_headcount_forbidden_for_employee(): void
    {
        $this->actingAsEmployee();

        $response = $this->getJson('/api/v1/reports/headcount');

        $response->assertForbidden();
    }

    public function test_headcount_counts_only_current_tenant_employees(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        Employee::factory()->count(2)->create(['company_id' => $companyA->id]);
        Employee::factory()->count(5)->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerA);

        $this->getJson('/api/v1/reports/headcount')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    public function test_turnover_returns_hired_and_terminated(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/turnover?months=6');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['period_months', 'hired', 'terminated'],
            ]);
    }

    public function test_absenteeism_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/absenteeism');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_payroll_summary_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/payroll-summary');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_overtime_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/overtime');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_recruitment_pipeline_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/recruitment-pipeline');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_training_completion_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/training-completion');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_loan_summary_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/loan-summary');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_demographic_breakdown_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/demographics');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_cost_analysis_returns_data(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/reports/cost-analysis');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
