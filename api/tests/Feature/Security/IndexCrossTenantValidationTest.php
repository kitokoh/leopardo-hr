<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class IndexCrossTenantValidationTest extends TestCase
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

    public function test_salary_advance_index_rejects_cross_tenant_employee_id(): void
    {
        $companyA = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);
        $companyB = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $employeeB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson("/api/v1/salary-advances?employee_id={$employeeB->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', 'Employé introuvable dans votre entreprise.');
    }

    public function test_absence_index_rejects_cross_tenant_employee_id(): void
    {
        $companyA = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);
        $companyB = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $employeeB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson("/api/v1/absences?employee_id={$employeeB->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', 'Employé introuvable dans votre entreprise.');
    }

    public function test_evaluation_index_rejects_cross_tenant_ids(): void
    {
        $companyA = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);
        $companyB = Company::factory()->create(['schema_name' => 'shared_tenants', 'tenancy_type' => 'shared']);

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $employeeB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($managerA);

        // Test employee_id
        $response = $this->getJson("/api/v1/evaluations?employee_id={$employeeB->id}");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', 'Employé introuvable dans votre entreprise.');

        // Test evaluator_id
        $response = $this->getJson("/api/v1/evaluations?evaluator_id={$employeeB->id}");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evaluator_id']);
        $response->assertJsonPath('errors.evaluator_id.0', 'Employé introuvable dans votre entreprise.');
    }
}
