<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TaskSecurityTest extends TestCase
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

    public function test_manager_cannot_create_task_associated_with_another_tenant_project(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $projectB = Project::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => 999, // Another tenant's employee
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Steal Data Task',
                'project_id' => $projectB->id,
                'due_date' => now()->addDays(7)->toIso8601String(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_manager_cannot_create_task_assigned_to_another_tenant_employee(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Cross-Tenant Assignment',
                'assigned_to' => [$employeeB->id],
                'due_date' => now()->addDays(7)->toIso8601String(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assigned_to.0']);
    }

    public function test_manager_cannot_update_task_to_another_tenant_project(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $taskA = Task::query()->forceCreate([
            'company_id' => $companyA->id,
            'title' => 'Task A',
            'created_by' => $managerA->id,
            'due_date' => now()->addDays(7),
        ]);

        $projectB = Project::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => 999,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/tasks/{$taskA->id}", [
                'project_id' => $projectB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_manager_cannot_filter_tasks_by_another_tenant_project(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $projectB = Project::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => 999,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/tasks?project_id={$projectB->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
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
