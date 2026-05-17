<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AIWorkflowTest extends TestCase
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

    public function test_prepare_payroll_workflow_returns_steps(): void
    {
        $company = Company::first();
        $this->assertNotNull($company);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/ai/workflows/prepare-payroll', [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'status',
                'summary' => ['employee_count', 'period'],
                'steps',
            ],
        ]);
    }

    public function test_weekly_report_workflow_returns_report(): void
    {
        $company = Company::first();
        $this->assertNotNull($company);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/ai/workflows/weekly-report');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'period' => ['start', 'end'],
                'headcount',
                'absences',
                'anomalies',
                'summary',
            ],
        ]);
    }

    public function test_employee_cannot_access_workflows(): void
    {
        $company = Company::first();
        $this->assertNotNull($company);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/ai/workflows/prepare-payroll', [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]);

        $response->assertForbidden();
    }
}
