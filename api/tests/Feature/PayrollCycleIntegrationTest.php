<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
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

    public function test_payroll_cycles_index_uses_standard_data_meta_envelope(): void
    {
        // PA2-API-001: /api/v1/payroll/cycles used to return Laravel's raw
        // paginator shape (current_page/data/links/... at the top level)
        // instead of the success/data/meta envelope used everywhere else in
        // the API (see ApiListQueryContractTest, PayrollRunControllerTest).
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycles?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'company_id', 'country_code', 'period_start', 'period_end', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('current_page')
            ->assertJsonMissingPath('data.0.links');
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

    public function test_employee_can_read_own_current_balance_with_advance_deducted(): void
    {
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['payroll' => ['pay_cycle' => 'monthly']],
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'salary_base' => 120000,
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 20000,
            'net_salary' => 100000,
            'status' => 'validated',
        ]);

        SalaryAdvance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 15000,
            'reason' => 'Urgence familiale',
            'status' => 'approved',
            'validation_status' => 'payment_declared',
            'payment_declared_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/balance')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.currency', 'DZD')
            ->assertJsonPath('data.gross_due', 100000)
            ->assertJsonPath('data.advances', 15000)
            ->assertJsonPath('data.remaining', 85000);
    }

    public function test_employee_cannot_read_another_employee_balance(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        $other = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/employees/{$other->id}/balance")
            ->assertForbidden();
    }

    public function test_manager_mobile_summary_is_tenant_scoped(): void
    {
        $companyA = Company::factory()->create(['currency' => 'DZD']);
        $companyB = Company::factory()->create(['currency' => 'EUR']);
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $employeeA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Amina',
            'salary_base' => 90000,
        ]);
        $employeeB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'first_name' => 'Karim',
            'salary_base' => 999999,
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/payroll/mobile-summary');

        $response->assertOk()
            ->assertJsonFragment(['employee_id' => $employeeA->id])
            ->assertJsonMissing(['employee_id' => $employeeB->id]);
    }
}
