<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
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
 * Couvre le critère d'acceptation : une correspondance n'est VALIDE que
 * si les horaires sont compatibles (arrivée leg1 + délai ≤ départ leg2) ;
 * la vente groupée produit deux réservations liées (billets séparés).
 */
class TravelConnectionApiTest extends TestCase
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
     * Ville A → B → C : leg1 (A→B, arrive 10:00) et leg2 (B→C, part 11:00)
     * sont compatibles (60 min de correspondance).
     *
     * @return array{cities: array<string, int>, leg1: int, leg2: int, class: int}
     */
    private function fixtures(Company $company, ?string $leg2Departure = '11:00:00'): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $leg2Departure): array {
            $cityA = TravelCity::factory()->create();
            $cityB = TravelCity::factory()->create();
            $cityC = TravelCity::factory()->create();

            $routeAB = TravelRoute::factory()->create(['origin_city_id' => $cityA->id, 'destination_city_id' => $cityB->id]);
            $routeBC = TravelRoute::factory()->create(['origin_city_id' => $cityB->id, 'destination_city_id' => $cityC->id]);

            $class = TravelClass::factory()->create();
            $date = now()->addDays(3)->toDateString();

            $tripAB = TravelTrip::factory()->create([
                'status' => 'published',
                'route_id' => $routeAB->id,
                'departure_date' => $date,
                'departure_time' => '08:00:00',
                'arrival_date' => $date,
                'arrival_time' => '10:00:00',
                'total_seats' => 20,
            ]);
            $tripBC = TravelTrip::factory()->create([
                'status' => 'published',
                'route_id' => $routeBC->id,
                'departure_date' => $date,
                'departure_time' => $leg2Departure,
                'arrival_date' => $date,
                'arrival_time' => '13:00:00',
                'total_seats' => 20,
            ]);

            app(GenerateTripSeatsAction::class)->execute($tripAB);
            app(GenerateTripSeatsAction::class)->execute($tripBC);

            foreach ([$tripAB, $tripBC] as $trip) {
                TravelTripPrice::factory()->create([
                    'trip_id' => $trip->id,
                    'class_id' => $class->id,
                    'adult_price_minor' => 10000,
                ]);
            }

            return [
                'cities' => ['A' => $cityA->id, 'B' => $cityB->id, 'C' => $cityC->id],
                'leg1' => $tripAB->id,
                'leg2' => $tripBC->id,
                'class' => $class->id,
            ];
        });
    }

    public function test_compatible_connection_is_found(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->fixtures($company);

        $this->getJson('/api/v1/travel/shop/connections?'
            .'origin_city_id='.$fx['cities']['A']
            .'&destination_city_id='.$fx['cities']['C']
            .'&date='.now()->addDays(3)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.connection_minutes', 60)
            ->assertJsonPath('data.0.total_price_minor', 20000);
    }

    public function test_incompatible_connection_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Leg2 part à 10:20 → seulement 20 min de correspondance (< 45).
        $fx = $this->fixtures($company, leg2Departure: '10:20:00');

        $this->getJson('/api/v1/travel/shop/connections?'
            .'origin_city_id='.$fx['cities']['A']
            .'&destination_city_id='.$fx['cities']['C']
            .'&date='.now()->addDays(3)->toDateString())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_grouped_booking_creates_two_linked_bookings(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->fixtures($company);

        $response = $this->postJson('/api/v1/travel/shop/connections/book', [
            'leg1_trip_id' => $fx['leg1'],
            'leg2_trip_id' => $fx['leg2'],
            'idempotency_key' => 'conn-1',
            'leg1_passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $fx['class']]],
            'leg2_passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $fx['class']]],
        ])->assertStatus(201)->json('data');

        $this->assertNotNull($response['connection_group_id']);

        // Deux réservations distinctes (billets séparés), même groupe.
        $this->assertSame(2, TravelBooking::query()
            ->where('connection_group_id', $response['connection_group_id'])
            ->count());
    }
}
