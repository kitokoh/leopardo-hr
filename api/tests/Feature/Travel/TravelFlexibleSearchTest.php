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
 * TRAVEL-804 (#6095) — Recherche flexible (dates ± N jours).
 *
 * `flexible_days` (0..7) élargit la recherche autour de `departure_date` ;
 * les résultats sont bornés par la fenêtre et triés par prix croissant puis
 * date (le jour le moins cher d'abord).
 */
class TravelFlexibleSearchTest extends TestCase
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
     * Crée un trajet publié à la date donnée avec un tarif adulte donné.
     */
    private function tripOn(Company $company, string $date, int $price, string $code): TravelTrip
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($date, $price, $code): TravelTrip {
            $origin = TravelCity::factory()->create();
            $destination = TravelCity::factory()->create();
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);
            $class = TravelClass::factory()->create();

            $trip = TravelTrip::factory()->create([
                'code' => $code,
                'route_id' => $route->id,
                'departure_date' => $date,
                'departure_time' => '08:00',
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

    public function test_flexible_search_returns_dates_within_window_ordered_by_price(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // J-1 (cher), J (moyen), J+1 (pas cher), J+2 (hors fenêtre ±1).
        $this->tripOn($company, '2026-09-10', 20000, 'T-100');
        $this->tripOn($company, '2026-09-11', 15000, 'T-101');
        $this->tripOn($company, '2026-09-12', 12000, 'T-102');
        $this->tripOn($company, '2026-09-13', 18000, 'T-103');

        $response = $this->getJson('/api/v1/travel/trips/search?departure_date=2026-09-11&flexible_days=1')
            ->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        // Fenêtre [2026-09-10, 2026-09-12] : T-102 (12 000), T-101 (15 000),
        // T-100 (20 000) — tri par prix croissant ; T-103 exclu (J+2).
        $this->assertSame(['T-102', 'T-101', 'T-100'], $codes);
    }

    public function test_flexible_days_zero_keeps_exact_date_behavior(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->tripOn($company, '2026-09-11', 15000, 'T-101');
        $this->tripOn($company, '2026-09-12', 12000, 'T-102');

        $codes = collect($this->getJson('/api/v1/travel/trips/search?departure_date=2026-09-11&flexible_days=0')
            ->assertOk()
            ->json('data'))->pluck('code')->all();

        $this->assertSame(['T-101'], $codes);
    }

    public function test_flexible_days_is_bounded_to_seven(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->tripOn($company, '2026-09-01', 15000, 'T-201');
        $this->tripOn($company, '2026-09-15', 15000, 'T-202');

        // flexible_days=99 → borné à 7 : fenêtre [09-01, 09-15] impossible,
        // seul le trajet du 09-01 est dans [08-25, 09-08].
        $codes = collect($this->getJson('/api/v1/travel/trips/search?departure_date=2026-09-01&flexible_days=99')
            ->assertOk()
            ->json('data'))->pluck('code')->all();

        $this->assertSame(['T-201'], $codes);
    }
}
