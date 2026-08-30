<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-306 (#6036) — CRUD /travel/vehicles (flotte propre).
 *
 * Couvre le CRUD complet, la validation (capacité > 0), l'isolation
 * cross-tenant et le RBAC `travel.manage`.
 */
class TravelVehicleApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_principal_can_create_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-001',
            'registration_number' => 'LT-1234-AB',
            'seat_capacity' => 45,
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'BUS-001', 'seat_capacity' => 45]);
    }

    public function test_seat_capacity_must_be_positive(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-002',
            'seat_capacity' => 0,
        ])->assertStatus(422);
    }

    public function test_vehicle_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $vehicleId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelVehicle::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/vehicles/{$vehicleId}")->assertStatus(404);
    }

    public function test_update_and_delete_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelVehicle::factory()->create()->id;
        });

        $this->putJson("/api/v1/travel/vehicles/{$vehicleId}", ['seat_capacity' => 50])
            ->assertOk()
            ->assertJsonFragment(['seat_capacity' => 50]);

        $this->deleteJson("/api/v1/travel/vehicles/{$vehicleId}")->assertStatus(204);
    }

    public function test_employee_without_manage_role_cannot_create(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-003',
            'seat_capacity' => 45,
        ])->assertStatus(403);
    }

    public function test_carrier_must_belong_to_same_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $carrierId = app(TenantManager::class)->withinTenant($other, function (): int {
            return TravelCarrier::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-004',
            'seat_capacity' => 45,
            'carrier_id' => $carrierId,
        ])->assertStatus(422);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/vehicles')->assertStatus(401);
    }
}
