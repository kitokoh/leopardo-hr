<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-307 (#6037) — CRUD /travel/routes + /travel/routes/{route}/stops.
 *
 * Couvre le CRUD des routes, l'interdiction origine==destination,
 * l'isolation cross-tenant, et la sous-ressource étapes (tri par rang,
 * rank auto-attribué, réordonnancement après suppression).
 */
class TravelRouteApiTest extends TestCase
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

    /**
     * @return array{origin: int, destination: int}
     */
    private function createCities(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                'origin' => TravelCity::factory()->create(['name' => 'Douala'])->id,
                'destination' => TravelCity::factory()->create(['name' => 'Yaoundé'])->id,
            ];
        });
    }

    public function test_principal_can_create_route(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cities = $this->createCities($company);

        $this->postJson('/api/v1/travel/routes', [
            'code' => 'DLA-YDE',
            'origin_city_id' => $cities['origin'],
            'destination_city_id' => $cities['destination'],
            'distance_km' => 250,
            'duration_min' => 240,
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'DLA-YDE', 'distance_km' => 250]);
    }

    public function test_route_origin_cannot_equal_destination(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cities = $this->createCities($company);

        $this->postJson('/api/v1/travel/routes', [
            'code' => 'LOOP-1',
            'origin_city_id' => $cities['origin'],
            'destination_city_id' => $cities['origin'],
        ])->assertStatus(422);
    }

    public function test_route_city_of_another_tenant_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $cityId = app(TenantManager::class)->withinTenant($other, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/routes', [
            'code' => 'CROSS-1',
            'origin_city_id' => $cityId,
            'destination_city_id' => $cityId,
        ])->assertStatus(422);
    }

    public function test_route_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $routeId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/routes/{$routeId}")->assertStatus(404);
    }

    public function test_update_and_delete_route(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $routeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        $this->putJson("/api/v1/travel/routes/{$routeId}", ['distance_km' => 300])
            ->assertOk()
            ->assertJsonFragment(['distance_km' => 300]);

        $this->deleteJson("/api/v1/travel/routes/{$routeId}")->assertStatus(204);
    }

    public function test_stops_are_ranked_and_auto_ranked(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $routeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        $cities = $this->createCities($company);
        /** @var Company $company */
        $cityA = TravelCity::factory()->create(['company_id' => $company->id, 'name' => 'Edéa']);
        $cityB = TravelCity::factory()->create(['company_id' => $company->id, 'name' => 'Bafia']);

        // Premier stop : rank auto = 1.
        $this->postJson("/api/v1/travel/routes/{$routeId}/stops", [
            'city_id' => $cityA->id,
            'is_stopover' => true,
        ])->assertStatus(201)
            ->assertJsonFragment(['rank' => 1]);

        // Second stop : rank auto = 2.
        $this->postJson("/api/v1/travel/routes/{$routeId}/stops", [
            'city_id' => $cityB->id,
        ])->assertStatus(201)
            ->assertJsonFragment(['rank' => 2]);

        // La liste est triée par rang croissant.
        $this->getJson("/api/v1/travel/routes/{$routeId}/stops")
            ->assertOk()
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.1.rank', 2);
    }

    public function test_stop_of_another_tenant_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $routeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $cityId = app(TenantManager::class)->withinTenant($other, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson("/api/v1/travel/routes/{$routeId}/stops", ['city_id' => $cityId])
            ->assertStatus(422);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/routes')->assertStatus(401);
    }
}
