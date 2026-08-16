<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class OrgChartControllerTest extends TestCase
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

    public function test_index_returns_tree_structure(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
            'manager_id' => null,
        ]);
        Sanctum::actingAs($manager);

        Employee::factory()->create([
            'company_id' => $manager->company_id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $manager->id,
        ]);

        $response = $this->getJson('/api/v1/org-chart');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_subordinates_returns_direct_reports(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
            'manager_id' => null,
        ]);

        $subordinate = Employee::factory()->create([
            'company_id' => $manager->company_id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/org-chart/{$manager->id}/subordinates");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_chain_returns_hierarchy(): void
    {
        $company = Company::factory()->create();
        $director = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'manager_id' => null,
        ]);

        $midManager = Employee::factory()->create([
            'company_id' => $director->company_id,
            'role' => 'manager',
            'status' => 'active',
            'manager_id' => $director->id,
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $director->company_id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $midManager->id,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson("/api/v1/org-chart/{$employee->id}/manager-chain");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_chain_returns_404_for_nonexistent_employee(): void
    {
        $company = Company::factory()->create();
        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/org-chart/999999/manager-chain');

        $response->assertNotFound();
    }

    public function test_non_manager_cannot_query_another_employee_subordinates(): void
    {
        $company = Company::factory()->create();
        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $other = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/org-chart/{$other->id}/subordinates")->assertForbidden();
    }

    public function test_non_manager_cannot_query_another_employee_manager_chain(): void
    {
        $company = Company::factory()->create();
        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $other = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/org-chart/{$other->id}/manager-chain")->assertForbidden();
    }

    public function test_non_manager_can_query_own_subordinates_and_chain(): void
    {
        $company = Company::factory()->create();
        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => null,
        ]);

        Sanctum::actingAs($actor);

        // Comportement conservé : soi-même toujours autorisé (liste vide
        // pour un non-manager sans rapport).
        $this->getJson("/api/v1/org-chart/{$actor->id}/subordinates")->assertOk();
        $this->getJson("/api/v1/org-chart/{$actor->id}/manager-chain")->assertOk();
    }

    public function test_manager_can_query_any_employee_in_company(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/org-chart/{$employee->id}/subordinates")->assertOk();
        $this->getJson("/api/v1/org-chart/{$employee->id}/manager-chain")->assertOk();
    }

    public function test_dept_manager_cannot_query_employee_outside_department(): void
    {
        $company = Company::factory()->create();

        /** @var \App\Modules\HR\Domain\Models\Department $deptA */
        $deptA = \App\Modules\HR\Domain\Models\Department::query()->create(['name' => 'R&D A']);
        $deptA->company_id = $company->id;
        $deptA->save();

        /** @var \App\Modules\HR\Domain\Models\Department $deptB */
        $deptB = \App\Modules\HR\Domain\Models\Department::query()->create(['name' => 'R&D B']);
        $deptB->company_id = $company->id;
        $deptB->save();

        $deptManager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'dept',
            'department_id' => $deptA->id,
            'status' => 'active',
        ]);
        $outsideEmployee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'department_id' => $deptB->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($deptManager);

        $this->getJson("/api/v1/org-chart/{$outsideEmployee->id}/manager-chain")->assertForbidden();
        $this->getJson("/api/v1/org-chart/{$outsideEmployee->id}/subordinates")->assertForbidden();
    }

    public function test_tenant_isolation_org_chart(): void
    {
        $managerA = Employee::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $managerB = Employee::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/org-chart');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($managerB->id, $ids);
    }
}

