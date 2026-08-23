<?php

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Modules\HR\Domain\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Matrice RBAC HR (issue #5262) — chaque rôle × chaque ressource :
 * la matrice documentée dans docs/HR/RBAC_MATRIX.md doit correspondre à
 * l'application réelle. Toute fuite cross-rôle fait échouer ce fichier.
 */
class RbacMatrixTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_employee_cannot_list_employees(): void
    {
        [$company, , $employee] = $this->createActors();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/employees')->assertForbidden();
    }

    public function test_employee_can_view_own_record_with_own_salary_only(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($employee);

        $response = $this->getJson("/api/v1/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.salary_base', 60000);
    }

    public function test_employee_cannot_view_another_employee_record(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        $other = $this->createEmployee($company, 'other.rbac@a.test', 'employee', null);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/employees/{$other->id}")->assertForbidden();
    }

    public function test_employee_resource_masks_salary_for_foreign_viewer(): void
    {
        // Masquage défensif : un viewer non autorisé sur une fiche qui n'est
        // pas la sienne reçoit salary_* = null (fuite cross-rôle impossible).
        [$company, $manager, $employee] = $this->createActors();
        $target = $this->createEmployee($company, 'target.rbac@a.test', 'employee', null);

        $request = Request::create('/api/v1/employees', 'GET');
        $request->setUserResolver(fn (): Employee => $employee);

        /** @var array<string, mixed> $payload */
        $payload = (new EmployeeResource($target))->resolve($request);

        $this->assertNull($payload['salary_base']);
        $this->assertNull($payload['hourly_rate']);
    }

    public function test_manager_principal_sees_salaries_and_creates_employees(): void
    {
        [$company, $manager] = $this->createActors();

        Sanctum::actingAs($manager);

        $list = $this->getJson('/api/v1/employees');
        $list->assertOk();
        $this->assertNotNull($list->json('data.0.salary_base'));

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Nouveau',
            'last_name' => 'Employé',
            'email' => 'nouveau.rbac@a.test',
            'password' => 'password123',
            'contract_start' => '2026-09-01',
        ])->assertStatus(201);
    }

    public function test_department_scoped_manager_sees_only_own_department(): void
    {
        [$company, $manager] = $this->createActors();
        $ownDepartment = $this->createDepartment($company, 'Dept A', $manager);
        $otherDepartment = $this->createDepartment($company, 'Dept B', null);

        $deptManager = $this->createEmployee($company, 'dept.rbac@a.test', 'manager', 'dept');
        $deptManager->forceFill(['department_id' => $ownDepartment->id])->save();

        $inScope = $this->createEmployee($company, 'inscope.rbac@a.test', 'employee', null);
        $inScope->forceFill(['department_id' => $ownDepartment->id])->save();
        $outOfScope = $this->createEmployee($company, 'outofscope.rbac@a.test', 'employee', null);
        $outOfScope->forceFill(['department_id' => $otherDepartment->id])->save();

        Sanctum::actingAs($deptManager);

        $list = $this->getJson('/api/v1/employees');
        $list->assertOk();

        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($inScope->id, $ids);
        $this->assertNotContains($outOfScope->id, $ids);

        // Salaire visible dans le périmètre (PA2-SEC-002).
        $this->getJson("/api/v1/employees/{$inScope->id}")
            ->assertOk()
            ->assertJsonPath('data.salary_base', 60000);

        // Hors périmètre → 403 (policy EmployeePolicy::view).
        $this->getJson("/api/v1/employees/{$outOfScope->id}")->assertForbidden();
    }

    public function test_comptable_reads_salaries_but_cannot_edit_hr(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        $comptable = $this->createEmployee($company, 'comptable.rbac@a.test', 'manager', 'comptable');

        Sanctum::actingAs($comptable);

        // Lecture des salaires OK.
        $this->getJson('/api/v1/employees')->assertOk();
        $this->getJson("/api/v1/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.salary_base', 60000);

        // Édition dossier → 403 (pas principal/rh).
        $this->patchJson("/api/v1/employees/{$employee->id}", ['phone' => '0699000000'])->assertForbidden();

        // Création employé → 403.
        $this->postJson('/api/v1/employees', [
            'first_name' => 'Interdit',
            'last_name' => 'Comptable',
            'email' => 'interdit.rbac@a.test',
            'password' => 'password123',
        ])->assertForbidden();
    }

    public function test_cross_tenant_access_is_fail_closed(): void
    {
        [$companyA, $managerA] = $this->createActors();
        [$companyB, , $employeeB] = $this->createActors();

        Sanctum::actingAs($managerA);

        // Fiche d'un employé d'une autre société → 404 (jamais 403/200).
        $this->getJson("/api/v1/employees/{$employeeB->id}")->assertNotFound();
    }

    public function test_self_service_is_open_to_all_roles(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        $comptable = $this->createEmployee($company, 'comptable.self@a.test', 'manager', 'comptable');

        foreach ([$employee, $comptable, $manager] as $actor) {
            Sanctum::actingAs($actor);

            $this->getJson('/api/v1/me/career')->assertOk();
            $this->getJson('/api/v1/me/contracts')->assertOk();
        }
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function createActors(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'DZ',
            'timezone' => 'UTC',
        ]);

        $manager = $this->createEmployee($company, 'manager.rbac@a.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.rbac@a.test', 'employee', null);

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
            'salary_base' => 60000,
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

    private function createDepartment(Company $company, string $name, ?Employee $manager): Department
    {
        $department = Department::create(['name' => $name, 'manager_id' => $manager?->id]);
        $department->company_id = $company->id;
        $department->save();

        return $department;
    }
}
