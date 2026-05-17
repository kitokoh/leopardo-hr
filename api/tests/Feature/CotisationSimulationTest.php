<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CotisationSimulationTest extends TestCase
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

    public function test_simulate_dz_cotisations(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 50000,
            'country_code' => 'DZ',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'country_code',
                'gross_salary',
                'employee_contributions',
                'employer_contributions',
                'total_employee_deduction',
                'total_employer_cost',
                'net_before_tax',
                'total_cost_employer',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals('DZ', $data['country_code']);
        $this->assertEquals(50000, $data['gross_salary']);
        $this->assertEquals(4500, $data['total_employee_deduction']);
    }

    public function test_simulate_ma_cotisations(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 10000,
            'country_code' => 'MA',
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('MA', $data['country_code']);
        $this->assertGreaterThan(0, $data['total_employee_deduction']);
    }

    public function test_employee_cannot_simulate(): void
    {
        $company = Company::factory()->create();

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 50000,
            'country_code' => 'DZ',
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_country_code_rejected(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 50000,
            'country_code' => 'XX',
        ]);

        $response->assertUnprocessable();
    }
}
