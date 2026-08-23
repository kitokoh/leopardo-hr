<?php

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Référentiels organisation (issue #5263 — complément de couverture HR) :
 * CRUD départements + positions, RBAC manager, isolation tenant.
 */
class OrgReferentialCrudTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_principal_crud_departments(): void
    {
        [$company, $manager] = $this->createActors();

        Sanctum::actingAs($manager);

        $created = $this->postJson('/api/v1/departments', ['name' => 'Production']);
        $created->assertStatus(201)->assertJsonPath('data.name', 'Production');

        $departmentId = $created->json('data.id');

        $this->getJson('/api/v1/departments')->assertOk();

        $this->putJson("/api/v1/departments/{$departmentId}", ['name' => 'Production & Logistique'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Production & Logistique');

        $this->deleteJson("/api/v1/departments/{$departmentId}")
            ->assertOk()
            ->assertJsonPath('message', __('errors.DEPARTMENT_DELETED'));

        $this->assertDatabaseMissing('departments', ['id' => $departmentId]);
    }

    public function test_manager_principal_crud_positions(): void
    {
        [$company, $manager] = $this->createActors();
        $department = $this->createDepartment($company, 'Prod', $manager);

        Sanctum::actingAs($manager);

        $created = $this->postJson('/api/v1/positions', [
            'name' => 'Opérateur',
            'department_id' => $department->id,
        ]);
        $created->assertStatus(201)->assertJsonPath('data.name', 'Opérateur');

        $positionId = $created->json('data.id');

        $this->getJson('/api/v1/positions')->assertOk();

        $this->patchJson("/api/v1/positions/{$positionId}", ['name' => 'Opérateur senior'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Opérateur senior');

        $this->deleteJson("/api/v1/positions/{$positionId}")
            ->assertOk()
            ->assertJsonPath('message', __('errors.POSITION_DELETED'));

        $this->assertDatabaseMissing('positions', ['id' => $positionId]);
    }

    public function test_employee_cannot_manage_referentials(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/departments', ['name' => 'Interdit'])->assertForbidden();
        $this->postJson('/api/v1/positions', ['name' => 'Interdit'])->assertForbidden();
        $this->deleteJson('/api/v1/departments/1')->assertForbidden();
    }

    public function test_cross_tenant_department_is_404(): void
    {
        [$companyA, $managerA] = $this->createActors();
        [, , $employeeB] = $this->createActors();

        $departmentB = $this->createDepartment((string) $employeeB->company_id, 'Dept B', null);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/departments/{$departmentB->id}")->assertNotFound();
        $this->putJson("/api/v1/departments/{$departmentB->id}", ['name' => 'Intrusion'])->assertNotFound();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function createActors(): array
    {
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'DZ',
            'timezone' => 'UTC',
        ]);

        $manager = $this->createEmployee($company, 'manager.org@a.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.org@a.test', 'employee', null);

        return [$company, $manager, $employee];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => strtoupper((string) strstr($email, '@', true)),
        ])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        /** @var Employee $employee */
        return $employee;
    }

    private function createDepartment(string $companyId, string $name, ?Employee $manager): Department
    {
        $department = Department::create(['name' => $name, 'manager_id' => $manager?->id]);
        $department->company_id = $companyId;
        $department->save();

        return $department;
    }
}
