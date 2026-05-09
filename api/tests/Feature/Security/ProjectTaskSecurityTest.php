<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ProjectTaskSecurityTest extends TestCase
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

    public function test_manager_cannot_create_project_with_members_from_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/projects', [
                'name' => 'Project A',
                'members' => [$employeeB->id],
            ]);

        // It should fail validation if employee_id doesn't belong to companyA
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['members.0']);
    }

    public function test_manager_cannot_update_project_with_members_from_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $projectA = Project::create([
            'company_id' => $companyA->id,
            'name' => 'Project A',
            'created_by' => $managerA->id,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/projects/{$projectA->id}", [
                'members' => [$employeeB->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['members.0']);
    }

    public function test_manager_cannot_create_task_with_members_from_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task A',
                'assigned_to' => [$employeeB->id],
                'due_date' => now()->addDays(7)->toIso8601String(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assigned_to.0']);
    }

    public function test_manager_cannot_create_task_with_project_from_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $projectB = Project::create([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => $managerB->id,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task A',
                'project_id' => $projectB->id,
                'due_date' => now()->addDays(7)->toIso8601String(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_manager_cannot_update_task_with_project_from_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $taskA = Task::create([
            'company_id' => $companyA->id,
            'title' => 'Task A',
            'created_by' => $managerA->id,
            'due_date' => now()->addDays(7),
        ]);

        $projectB = Project::create([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => $managerB->id,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/tasks/{$taskA->id}", [
                'project_id' => $projectB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_project_index_rejects_cross_tenant_status(): void
    {
        $companyA = $this->createCompany('Company A');
        $managerA = $this->createEmployee($companyA, 'manager', 'principal');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/projects?status=invalid');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_task_index_rejects_cross_tenant_project_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $projectB = Project::create([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'created_by' => $managerB->id,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/tasks?project_id=' . $projectB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function test_task_index_rejects_cross_tenant_assigned_to(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/tasks?assigned_to=' . $employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_non_manager_cannot_create_project(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/projects', [
                'name' => 'Project A',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_manager_non_creator_non_assigned_cannot_update_task(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        $task = Task::create([
            'company_id' => $company->id,
            'title' => 'Task A',
            'created_by' => $manager->id,
            'assigned_to' => [$employee1->id],
            'due_date' => now()->addDays(7),
        ]);

        // Employee2 is not manager, not creator, and not assigned
        $response = $this->actingAs($employee2, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(403);
    }

    public function test_assigned_employee_can_update_task(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee1 = $this->createEmployee($company, 'employee');

        $task = Task::create([
            'company_id' => $company->id,
            'title' => 'Task A',
            'created_by' => $manager->id,
            'assigned_to' => [$employee1->id],
            'due_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", [
                'title' => 'Updated by Employee',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated by Employee', $task->fresh()->title);
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
