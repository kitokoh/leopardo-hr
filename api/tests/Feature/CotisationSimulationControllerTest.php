<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class CotisationSimulationControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

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

