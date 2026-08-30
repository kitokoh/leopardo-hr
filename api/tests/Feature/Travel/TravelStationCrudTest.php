<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-302 (#6032) — CRUD des gares/terminaux.
 *
 * Couvre le CRUD complet, le RBAC (rôle manager requis en écriture) et
 * l'isolation cross-tenant : une station ou une ville d'un autre tenant
 * renvoie systématiquement 404, jamais 403.
 */
class TravelStationCrudTest extends TestCase
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

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_create_station_with_city_of_another_tenant_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $foreignCityId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->postJson('/api/v1/travel/stations', [
            'code' => 'GAR-001',
            'name' => 'Gare Centrale',
            'city_id' => $foreignCityId,
        ])->assertStatus(422);
    }

    public function test_principal_can_create_station(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/stations', [
            'code' => 'GAR-001',
            'name' => 'Gare Centrale',
            'city_id' => $cityId,
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'GAR-001']);
    }

    public function test_ordinary_employee_cannot_create_station(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->ordinaryEmployee($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/stations', [
            'code' => 'GAR-001',
            'name' => 'Gare Centrale',
            'city_id' => $cityId,
        ])->assertStatus(403);
    }

    public function test_show_station_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $stationId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelStation::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/stations/{$stationId}")->assertStatus(404);
    }

    public function test_update_and_delete_station(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $stationId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return TravelStation::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/travel/stations/{$stationId}", ['name' => 'Nouvelle Gare'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Nouvelle Gare']);

        $this->deleteJson("/api/v1/travel/stations/{$stationId}")->assertStatus(204);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/stations')->assertStatus(401);
    }
}
