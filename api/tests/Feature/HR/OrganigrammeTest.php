<?php

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2594) : GET /departments/{department}/hierarchy
 * — l'organigramme par département des apps mobiles (manager/hr) 404ait.
 * L'endpoint retourne le département + manager + employés actifs, scopé tenant.
 */
class OrganigrammeTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_hierarchy_returns_department_manager_and_employees(): void
    {
        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        $this->assertInstanceOf(Company::class, $company);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->assertInstanceOf(Employee::class, $manager);

        $department = Department::create([
            'company_id' => $company->id,
            'name' => 'Operations',
            'manager_id' => $manager->id,
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $department->id,
        ]);
        $this->assertInstanceOf(Employee::class, $employee);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/departments/{$department->id}/hierarchy");

        $response->assertStatus(200)
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.employee_count', 1)
            ->assertJsonPath('data.employees.0.id', $employee->id);
    }

    public function test_hierarchy_returns_empty_tree_for_department_without_employees(): void
    {
        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        $this->assertInstanceOf(Company::class, $company);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->assertInstanceOf(Employee::class, $manager);

        $department = Department::create([
            'company_id' => $company->id,
            'name' => 'Vide',
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/departments/{$department->id}/hierarchy")
            ->assertStatus(200)
            ->assertJsonPath('data.employee_count', 0);
    }

    public function test_hierarchy_is_scoped_to_tenant(): void
    {
        $companyA = Company::factory()->create(['schema_name' => 'shared_tenants']);
        $companyB = Company::factory()->create(['schema_name' => 'shared_tenants']);
        $this->assertInstanceOf(Company::class, $companyA);
        $this->assertInstanceOf(Company::class, $companyB);

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->assertInstanceOf(Employee::class, $managerA);

        $departmentB = Department::create([
            'company_id' => $companyB->id,
            'name' => 'Autre tenant',
        ]);

        // Le manager A ne peut pas voir le département du tenant B (403 via la
        // Policy DepartmentPolicy::view — isolation garantie).
        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/departments/{$departmentB->id}/hierarchy");
        $this->assertContains($response->status(), [403, 404]);
    }
}
