<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlanningControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_weekly_optimization_requires_authentication(): void
    {
        $this->getJson('/api/v1/planning/weekly-optimization')
            ->assertUnauthorized();
    }

    public function test_weekly_optimization_accessible_by_manager(): void
    {
        // Les routes planning portent api.manager (outil manager) — un
        // employé simple reçoit 403, un manager accède (issue #5201).
        $this->actingAs($this->manager)
            ->getJson('/api/v1/planning/weekly-optimization')
            ->assertOk();

        $this->actingAs($this->employee)
            ->getJson('/api/v1/planning/weekly-optimization')
            ->assertForbidden();
    }

    public function test_shift_rebalancing_requires_authentication(): void
    {
        $this->getJson('/api/v1/planning/shift-rebalancing')
            ->assertUnauthorized();
    }

    public function test_shift_rebalancing_accessible_by_manager(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/api/v1/planning/shift-rebalancing')
            ->assertOk();

        $this->actingAs($this->employee)
            ->getJson('/api/v1/planning/shift-rebalancing')
            ->assertForbidden();
    }
}
