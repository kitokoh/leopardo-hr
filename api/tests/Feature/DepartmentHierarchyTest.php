<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class DepartmentHierarchyTest extends TestCase
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

    public function test_manager_can_get_department_hierarchy(): void
    {
        $company = Company::factory()->create();
        $dept = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'R&D',
        ]);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
            'manager_id' => null,
            'department_id' => $dept->id,
        ]);
        $dept->update(['manager_id' => $manager->id]);

        $lead = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $manager->id,
            'department_id' => $dept->id,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $lead->id,
            'department_id' => $dept->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/departments/{$dept->id}/hierarchy");

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'first_name', 'children']]])
            ->assertJsonPath('data.0.id', $manager->id)
            ->assertJsonCount(1, 'data.0.children');
    }

    public function test_hierarchy_is_scoped_to_tenant(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $deptA = Department::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Dept A',
        ]);

        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'manager_id' => null,
        ]);

        Sanctum::actingAs($managerB);

        // Cross-tenant : 404, jamais de fuite de données.
        $this->getJson("/api/v1/departments/{$deptA->id}/hierarchy")->assertNotFound();
    }

    public function test_employee_cannot_access_hierarchy(): void
    {
        $company = Company::factory()->create();
        $dept = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Ops',
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => null,
            'department_id' => $dept->id,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/departments/{$dept->id}/hierarchy")->assertForbidden();
    }
}
