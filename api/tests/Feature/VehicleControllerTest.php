<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Fleet\Domain\Models\Vehicle;
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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

    public function test_delete_message_is_localized(): void
    {
        // #4812 (audit 2026-08-17) : littéral EN « Vehicle deleted. » déplacé
        // au catalogue errors.* — la réponse suit la locale (Accept-Language).
        // Locale résolue par SetLocale : pour un utilisateur authentifié,
        // c'est la langue de l'entreprise qui gagne (pas le header).
        /** @var Company $company */
        $company = Company::factory()->create(['language' => 'en']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $vehicle = Vehicle::create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-9999-Z',
            'brand' => 'Renault',
            'model' => 'Kangoo',
            'year' => 2022,
            'type' => 'van',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->withHeader('Accept-Language', 'en')
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('message', __('errors.VEHICLE_DELETED', [], 'en'));

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_per_page_is_capped_at_100(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Vehicle::create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-9999-Z',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2023,
            'type' => 'car',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        // #3059 : per_page non borné → un client peut demander des pages énormes.
        $response = $this->getJson('/api/v1/vehicles?per_page=500');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);

        $capped = $this->getJson('/api/v1/vehicles?per_page=0');
        $capped->assertOk();
        $capped->assertJsonPath('meta.per_page', 1);
    }

    public function test_manager_can_create_vehicle(): void
    {
        /** @var Company $company */
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        /** @var Employee $manager */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $vehicle = Vehicle::create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-DEL',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();
    }

    public function test_invalid_list_filters_return_validation_error(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/vehicles?status=unknown&type=spaceship&per_page=not-a-number')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'type', 'per_page']);
    }

    public function test_filter_vehicles_by_status(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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
        /** @var Company $company */
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/vehicles', [
            'plate_number' => 'XX-9999',
            'type' => 'spaceship',
        ])->assertStatus(422);
    }

    public function test_assign_rejects_employee_from_another_company(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $foreignEmployee */
        $foreignEmployee = Employee::factory()->create(['company_id' => $companyB->id]);

        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::create([
            'company_id' => $companyA->id,
            'plate_number' => 'DZ-4788-X',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'type' => 'van',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        // #4788 : assigner un employé d'une AUTRE société doit être rejeté (422).
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/assign", [
            'employee_id' => $foreignEmployee->id,
            'start_date' => '2026-08-17',
            'reason' => 'test cross-tenant',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseMissing('vehicle_assignments', ['employee_id' => $foreignEmployee->id]);
    }
}
