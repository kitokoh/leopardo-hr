<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Vehicle;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
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

    public function test_manager_can_list_vehicles(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Vehicle::create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-1234-A',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'type' => 'van',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/vehicles');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_manager_can_create_vehicle(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/vehicles', [
            'plate_number' => 'FR-5678-B',
            'brand' => 'Renault',
            'model' => 'Kangoo',
            'year' => 2025,
            'type' => 'van',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.plate_number', 'FR-5678-B');
    }

    public function test_manager_can_update_vehicle(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $vehicle = Vehicle::create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-OLD',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/vehicles/{$vehicle->id}", [
            'plate_number' => 'DZ-NEW',
            'mileage' => 50000,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.plate_number', 'DZ-NEW');
    }

    public function test_manager_can_delete_vehicle(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $vehicle = Vehicle::create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-DEL',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();
    }

    public function test_filter_vehicles_by_status(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Vehicle::create(['company_id' => $company->id, 'plate_number' => 'A', 'status' => 'active']);
        Vehicle::create(['company_id' => $company->id, 'plate_number' => 'B', 'status' => 'maintenance']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/vehicles?status=active');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_invalid_vehicle_type_returns_validation_error(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/vehicles', [
            'plate_number' => 'XX-9999',
            'type' => 'spaceship',
        ])->assertStatus(422);
    }
}
