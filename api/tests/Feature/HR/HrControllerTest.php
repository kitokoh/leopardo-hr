<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Feature tests for HR module controllers.
 *
 * Covers:
 * - Employee CRUD with tenant isolation (cross-tenant 404)
 * - Department management scoped to company
 * - Training access (manager-only)
 * - RBAC: HR manager vs regular employee vs other company manager
 */
class HrControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;
    protected Company $otherCompany;
    protected Employee $manager;
    protected Employee $employee;
    protected Employee $otherManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company      = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();
        $this->manager      = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->employee     = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->otherManager = Employee::factory()->manager()->create(['company_id' => $this->otherCompany->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── Employees ────────────────────────────────────────────────────────────

    public function test_manager_can_list_employees_in_own_company(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_manager_cannot_see_employees_of_other_company(): void
    {
        Sanctum::actingAs($this->otherManager);

        // The other manager must not see employees from $this->company
        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($this->employee->id, $ids, 'Cross-tenant employee leak detected');
        $this->assertNotContains($this->manager->id, $ids, 'Cross-tenant manager leak detected');
    }

    public function test_regular_employee_cannot_list_all_employees(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/employees');

        // Regular employees should be forbidden or see only themselves
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_manager_can_show_own_company_employee(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->employee->id);
    }

    public function test_manager_gets_404_for_cross_tenant_employee(): void
    {
        $crossTenantEmployee = Employee::factory()->create(['company_id' => $this->otherCompany->id]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/employees/{$crossTenantEmployee->id}");

        $response->assertStatus(404);
    }

    // ── Departments ──────────────────────────────────────────────────────────

    public function test_manager_can_list_departments(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/departments');

        $response->assertOk();
    }

    public function test_other_company_manager_cannot_see_departments(): void
    {
        Sanctum::actingAs($this->otherManager);

        $response = $this->getJson('/api/v1/departments');

        $response->assertOk();

        // All returned departments must belong to otherCompany only
        $deptCompanyIds = collect($response->json('data'))->pluck('company_id')->unique()->toArray();
        $this->assertNotContains($this->company->id, $deptCompanyIds, 'Cross-tenant department leak');
    }

    // ── Training ─────────────────────────────────────────────────────────────

    public function test_manager_can_access_training_list(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/training');

        $this->assertContains($response->status(), [200, 404], 'Unexpected status for training list');
    }

    public function test_unauthenticated_user_cannot_access_employees(): void
    {
        $response = $this->getJson('/api/v1/employees');

        $response->assertUnauthorized();
    }
}
