<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
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

    public function test_manager_can_list_pay_slips_for_run(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/payroll-runs/{$run->id}/pay-slips");
        $response->assertOk();
    }

    public function test_employee_can_view_own_pay_slips(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/pay-slips');
        $response->assertOk();
    }

    public function test_employee_can_view_own_pay_slip_detail(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'gross_salary' => 50000,
            'total_deductions' => 10000,
            'net_salary' => 40000,
            'status' => 'validated',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson("/api/v1/me/pay-slips/{$slip->id}");
        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_pay_slips(): void
    {
        $this->getJson('/api/v1/me/pay-slips')->assertStatus(401);
    }
}
