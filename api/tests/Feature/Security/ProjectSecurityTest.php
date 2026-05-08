<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ProjectSecurityTest extends TestCase
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

    public function test_manager_cannot_create_project_with_another_tenant_members(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/projects', [
                'name' => 'Cross-Tenant Project',
                'members' => [$employeeB->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['members.0']);
    }

    public function test_manager_cannot_update_project_with_another_tenant_members(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $projectA = Project::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Project A',
            'created_by' => $managerA->id,
        ]);

        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/projects/{$projectA->id}", [
                'members' => [$employeeB->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['members.0']);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(Str::random(8)).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createEmployee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
