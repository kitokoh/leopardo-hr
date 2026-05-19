<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlanningControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

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

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
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

    public function test_weekly_optimization_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/planning/weekly-optimization')
            ->assertForbidden();
    }

    public function test_shift_rebalancing_requires_authentication(): void
    {
        $this->getJson('/api/v1/planning/shift-rebalancing')
            ->assertUnauthorized();
    }

    public function test_shift_rebalancing_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/planning/shift-rebalancing')
            ->assertForbidden();
    }
}
