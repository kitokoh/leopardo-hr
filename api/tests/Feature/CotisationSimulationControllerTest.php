<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CotisationSimulationControllerTest extends TestCase
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

    public function test_simulate_requires_authentication(): void
    {
        $this->postJson('/api/v1/cotisation-simulation', [])
            ->assertUnauthorized();
    }

    public function test_simulate_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/cotisation-simulation', [
                'gross_salary' => 50000,
                'country_code' => 'DZ',
            ])
            ->assertForbidden();
    }

    public function test_simulate_validates_required_fields(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/cotisation-simulation', [])
            ->assertUnprocessable();
    }

    public function test_simulate_validates_country_code(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/cotisation-simulation', [
                'gross_salary' => 50000,
                'country_code' => 'INVALID',
            ])
            ->assertUnprocessable();
    }
}
