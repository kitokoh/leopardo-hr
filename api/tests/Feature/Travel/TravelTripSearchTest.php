<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-311 (#6041) — GET /travel/trips/search (recherche interne).
 *
 * Filtres combinés (villes d'origine/destination via la route, date ou
 * plage, moyen de transport, statut, fourchette de prix), pagination,
 * isolation cross-tenant.
 */
class TravelTripSearchTest extends TestCase
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

    public function test_search_returns_matching_trips(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $date = now()->addDays(5)->toDateString();

        [$originId, $destId] = app(TenantManager::class)->withinTenant($company, function () use ($date): array {
            $origin = TravelCity::factory()->create(['name' => 'Douala']);
            $dest = TravelCity::factory()->create(['name' => 'Yaoundé']);

            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $dest->id,
            ]);

            TravelTrip::factory()->create([
                'route_id' => $route->id,
                'departure_date' => $date,
                'departure_time' => '08:00:00',
                'status' => 'published',
            ]);
            // Trajet d'une autre date : ne doit pas matcher le filtre date.
            TravelTrip::factory()->create([
                'route_id' => $route->id,
                'departure_date' => now()->addDays(20)->toDateString(),
                'status' => 'published',
            ]);

            return [$origin->id, $dest->id];
        });

        $this->getJson('/api/v1/travel/trips/search')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/travel/trips/search?departure_date='.$date)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/travel/trips/search?origin_city_id='.$originId.'&destination_city_id='.$destId)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/travel/trips/search?status=draft')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_filters_by_price_range(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $trip = TravelTrip::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'adult_price_minor' => 15000,
            ]);
        });

        $this->getJson('/api/v1/travel/trips/search?price_min=10000&price_max=20000')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/travel/trips/search?price_min=50000')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_is_tenant_scoped(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        app(TenantManager::class)->withinTenant($companyB, function (): void {
            TravelTrip::factory()->create();
        });

        $this->principal($companyA);

        $this->getJson('/api/v1/travel/trips/search')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/trips/search')->assertStatus(401);
    }
}
