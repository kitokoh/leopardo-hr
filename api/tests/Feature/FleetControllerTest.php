<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class FleetControllerTest extends TestCase
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

    public function test_manager_can_view_fleet_overview(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/overview');
        $response->assertOk();
    }

    public function test_manager_can_view_fuel_report(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/reports/fuel');
        $response->assertOk();
    }

    public function test_manager_can_view_mileage_report(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/reports/mileage');
        $response->assertOk();
    }

    public function test_manager_can_view_maintenance_due(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/reports/maintenance-due');
        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_fleet(): void
    {
        $this->getJson('/api/v1/fleet/overview')->assertStatus(401);
    }
}
