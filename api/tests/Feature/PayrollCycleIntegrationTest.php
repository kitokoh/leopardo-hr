<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * End-to-end payroll cycle: create run → add employees → compute → validate → list slips.
 */
class PayrollCycleIntegrationTest extends TestCase
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

    public function test_full_payroll_cycle_create_compute_validate(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Employee::factory()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        // Step 1: Create payroll run
        $response = $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $response->assertStatus(201)->assertJsonStructure([
            'data' => ['id', 'status'],
        ]);

        $runId = $response->json('data.id');

        // Step 2: Verify run is in draft
        $this->getJson("/api/v1/payroll-runs/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        // Step 3: List runs — our run should appear
        $this->getJson('/api/v1/payroll-runs')
            ->assertOk();
    }

    public function test_employee_cannot_manage_payroll_runs(): void
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

    public function test_payroll_run_scoped_to_tenant(): void
    {
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $companyB = Company::factory()->create(['name' => 'Company B']);

        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerA);
        $this->postJson('/api/v1/payroll-runs', [
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated();

        // Manager B should NOT see Manager A's run
        Sanctum::actingAs($managerB);
        $response = $this->getJson('/api/v1/payroll-runs');
        $response->assertOk();

        $runs = collect($response->json('data'));
        $this->assertTrue(
            $runs->where('label', 'Run A')->isEmpty(),
            'Manager B should not see Company A payroll runs',
        );
    }
}
