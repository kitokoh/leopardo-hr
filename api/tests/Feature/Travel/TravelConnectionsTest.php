<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-809 (#6099) — Correspondances (recherche multi-trajets).
 *
 * Une correspondance n'est valide que si les horaires sont compatibles :
 * départ(B) ≥ arrivée(A) + 45 min. Prix total = somme des tarifs adultes.
 */
class TravelConnectionsTest extends TestCase
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
     * Trajet publié entre deux villes à une heure donnée.
     */
    private function tripBetween(Company $company, TravelCity $origin, TravelCity $destination, string $date, string $departure, string $arrival, int $price, string $code): TravelTrip
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($origin, $destination, $date, $departure, $arrival, $price, $code): TravelTrip {
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);
            $class = TravelClass::factory()->create();

            $trip = TravelTrip::factory()->create([
                'code' => $code,
                'route_id' => $route->id,
                'departure_date' => $date,
                'departure_time' => $departure,
                'arrival_date' => $date,
                'arrival_time' => $arrival,
                'status' => 'published',
                'total_seats' => 40,
            ]);

            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => $price,
                'child_price_minor' => $price,
            ]);

            return $trip;
        });
    }

    public function test_valid_connection_is_returned_with_compatible_schedule(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cities = app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                TravelCity::factory()->create(['name' => 'Yaoundé']),
                TravelCity::factory()->create(['name' => 'Douala']),
                TravelCity::factory()->create(['name' => 'Bafoussam']),
            ];
        });
        [$yaounde, $douala, $bafoussam] = $cities;

        // A : Yaoundé 08:00 → Douala 10:00 ; B : Douala 11:00 → Bafoussam 13:00
        // → correspondance 60 min ≥ 45 min → valide.
        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $douala, $bafoussam, '2026-10-01', '11:00', '13:00', 8000, 'LEG-B');

        $response = $this->getJson('/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id.'&destination_city_id='.$bafoussam->id.'&date=2026-10-01')
            ->assertOk();

        $connections = $response->json('data');

        $this->assertCount(1, $connections);
        $this->assertSame('LEG-A', $connections[0]['first']['code']);
        $this->assertSame('LEG-B', $connections[0]['second']['code']);
        $this->assertSame(60, $connections[0]['connection_minutes']);
        $this->assertSame(18000, $connections[0]['total_price_minor']);
    }

    public function test_incompatible_schedule_is_excluded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cities = app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                TravelCity::factory()->create(['name' => 'Yaoundé']),
                TravelCity::factory()->create(['name' => 'Douala']),
                TravelCity::factory()->create(['name' => 'Bafoussam']),
            ];
        });
        [$yaounde, $douala, $bafoussam] = $cities;

        // A arrive à 10:00, B part à 10:20 → 20 min < 45 min → exclu.
        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $douala, $bafoussam, '2026-10-01', '10:20', '12:20', 8000, 'LEG-B');

        $this->getJson('/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id.'&destination_city_id='.$bafoussam->id.'&date=2026-10-01')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_connections_require_same_intermediate_city(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cities = app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                TravelCity::factory()->create(['name' => 'Yaoundé']),
                TravelCity::factory()->create(['name' => 'Douala']),
                TravelCity::factory()->create(['name' => 'Bafoussam']),
                TravelCity::factory()->create(['name' => 'Kribi']),
            ];
        });
        [$yaounde, $douala, $bafoussam, $kribi] = $cities;

        // A : Yaoundé → Douala ; B : Kribi → Bafoussam — pas de ville commune.
        $this->tripBetween($company, $yaounde, $douala, '2026-10-01', '08:00', '10:00', 10000, 'LEG-A');
        $this->tripBetween($company, $kribi, $bafoussam, '2026-10-01', '11:00', '13:00', 8000, 'LEG-B');

        $this->getJson('/api/v1/travel/trips/connections?origin_city_id='.$yaounde->id.'&destination_city_id='.$bafoussam->id.'&date=2026-10-01')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_connections_require_parameters(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->getJson('/api/v1/travel/trips/connections?date=2026-10-01')
            ->assertStatus(422);
    }
}
