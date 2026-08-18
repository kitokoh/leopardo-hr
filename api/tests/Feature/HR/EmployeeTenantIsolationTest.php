<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #3232 — EmployeePolicy cross-tenant : view/update/archive.
 *
 * Un manager (principal/RH) du tenant A ne doit jamais pouvoir lire,
 * modifier ou archiver un employé du tenant B.
 *
 * Défense en deux couches :
 *   1. Le global scope `BelongsToCompany` (current_company lié par le
 *      middleware `tenant`) fait échouer le `findOrFail` du controller
 *      sur un employé étranger → 404 (contrat anti-énumération, cf.
 *      PayrollTenantIsolationTest).
 *   2. La policy `EmployeePolicy` refuse explicitement tout target dont
 *      le `company_id` diffère de l'acteur → false (403 au niveau
 *      authorize) même si le scope venait à être contourné. Le niveau
 *      policy est couvert unitairement par
 *      `RbacManagerRoleMatrixTest::test_employee_policy_*_denied_across_companies`.
 */
class EmployeeTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(string $slug, string $email): Company
    {
        return Company::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $email,
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);
    }

    private function makeManager(Company $company, string $email, string $managerRole): Employee
    {
        $sensitiveEmployee1 = new Employee([
            'first_name' => 'Manager',
            'last_name' => ucfirst(str_replace(['@', '-'], ' ', $email)),
            'email' => $email,
        ]);
        $sensitiveEmployee1->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee1->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();
        return $sensitiveEmployee1;
    }

    private function makeEmployee(Company $company, string $email): Employee
    {
        $sensitiveEmployee0 = new Employee([
            'first_name' => 'Employe',
            'last_name' => ucfirst(str_replace(['@', '-'], ' ', $email)),
            'email' => $email,
        ]);
        $sensitiveEmployee0->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee0->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();
        return $sensitiveEmployee0;
    }

    /**
     * @return array{companyA: Company, companyB: Company, principalA: Employee, hrA: Employee, employeeB: Employee}
     */
    private function seedTwoTenants(): array
    {
        $companyA = $this->makeCompany('tenant-a', 'a@company.test');
        $companyB = $this->makeCompany('tenant-b', 'b@company.test');

        $principalA = $this->makeManager($companyA, 'principal@a.test', 'principal');
        $hrA = $this->makeManager($companyA, 'rh@a.test', 'rh');
        $employeeB = $this->makeEmployee($companyB, 'employee@b.test');

        return [
            'companyA' => $companyA,
            'companyB' => $companyB,
            'principalA' => $principalA,
            'hrA' => $hrA,
            'employeeB' => $employeeB,
        ];
    }

    /** @return array<string, string> */
    private function authHeader(Employee $actor): array
    {
        return ['Authorization' => 'Bearer '.$actor->createToken('tests')->plainTextToken];
    }

    public function test_principal_of_tenant_a_cannot_view_employee_of_tenant_b(): void
    {
        $seed = $this->seedTwoTenants();

        $response = $this->withHeaders($this->authHeader($seed['principalA']))
            ->getJson('/api/v1/employees/'.$seed['employeeB']->id);

        $response->assertNotFound();

        // Sanity : le même acteur voit bien ses propres employés.
        $ownEmployee = $this->makeEmployee($seed['companyA'], 'own@a.test');
        $this->withHeaders($this->authHeader($seed['principalA']))
            ->getJson('/api/v1/employees/'.$ownEmployee->id)
            ->assertOk();
    }

    public function test_principal_of_tenant_a_cannot_update_employee_of_tenant_b(): void
    {
        $seed = $this->seedTwoTenants();

        $response = $this->withHeaders($this->authHeader($seed['principalA']))
            ->putJson('/api/v1/employees/'.$seed['employeeB']->id, ['first_name' => 'Vol']);

        $response->assertNotFound();

        $this->assertDatabaseHas('employees', [
            'id' => $seed['employeeB']->id,
            'first_name' => 'Employe',
        ]);
    }

    public function test_rh_of_tenant_a_cannot_archive_employee_of_tenant_b(): void
    {
        $seed = $this->seedTwoTenants();

        $response = $this->withHeaders($this->authHeader($seed['hrA']))
            ->postJson('/api/v1/employees/'.$seed['employeeB']->id.'/archive');

        $response->assertNotFound();

        $this->assertDatabaseHas('employees', [
            'id' => $seed['employeeB']->id,
            'status' => 'active',
        ]);
    }
}
