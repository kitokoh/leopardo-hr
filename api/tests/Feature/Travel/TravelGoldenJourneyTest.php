<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1007 / TRAVEL-043 (#6120/#5997) — Golden journey GJ-TRAVEL-01.
 *
 * Parcours ROYAL de bout en bout : activation → référentiel (ville, route,
 * compagnie) → trajet + tarifs → publication → réservation guichet →
 * confirmation → billets → check-in → rapports. Chaque étape valide un
 * invariant de la verticale.
 */
class TravelGoldenJourneyTest extends TestCase
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

    public function test_golden_journey_gj_travel_01(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // 1. Référentiel géographique (seed via factory tenant-scoped).
        $cityIds = app(TenantManager::class)->withinTenant($company, function (): array {
            return [
                TravelCity::factory()->create(['name' => 'Douala'])->id,
                TravelCity::factory()->create(['name' => 'Yaoundé'])->id,
            ];
        });

        // 2. Compagnie + route + classe.
        $carrierId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCarrier::factory()->create()->id;
        });

        $routeId = app(TenantManager::class)->withinTenant($company, function () use ($cityIds): int {
            return TravelRoute::factory()->create([
                'origin_city_id' => $cityIds[0],
                'destination_city_id' => $cityIds[1],
            ])->id;
        });

        $classId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelClass::factory()->create()->id;
        });

        // 3. Trajet daté + tarif, publié via l'API.
        $trip = $this->postJson('/api/v1/travel/trips', [
            'code' => 'DLA-YDE-001',
            'route_id' => $routeId,
            'carrier_id' => $carrierId,
            'departure_date' => now()->addDays(7)->toDateString(),
            'departure_time' => '08:00',
            'arrival_date' => now()->addDays(7)->toDateString(),
            'arrival_time' => '11:30',
            'means_of_transport' => 'bus',
            'total_seats' => 40,
        ])->assertStatus(201)->json('data');

        $this->postJson('/api/v1/travel/trips/'.$trip['id'].'/prices', [
            'class_id' => $classId,
            'adult_price_minor' => 15000,
            'child_price_minor' => 9000,
            'currency' => 'XAF',
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/trips/'.$trip['id'].'/publish')->assertOk();

        // 4. Recherche boutique → réservation guichet multi-passagers.
        $this->getJson('/api/v1/travel/shop/trips')->assertOk()->assertJsonCount(1, 'data');

        $booking = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip['id'],
            'booking_source' => 'office',
            'idempotency_key' => 'gj-1',
            'passengers' => [
                ['full_name' => 'Alice', 'age_category' => 'adult', 'class_id' => $classId],
                ['full_name' => 'Bobby', 'age_category' => 'child', 'class_id' => $classId],
            ],
        ])->assertStatus(201)->json('data');

        // Total serveur : 15 000 + 9 000.
        $this->assertSame(24000, $booking['total_amount_minor']);

        // 5. Confirmation → billets → check-in.
        $this->postJson('/api/v1/travel/bookings/'.$booking['id'].'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $tickets = $this->postJson('/api/v1/travel/bookings/'.$booking['id'].'/issue-ticket')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $tickets);

        $this->postJson('/api/v1/travel/tickets/'.$tickets[0]['id'].'/check-in')
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');

        // 6. Manifeste trié par siège + rapports accessibles.
        $this->getJson('/api/v1/travel/trips/'.$trip['id'].'/manifest')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/travel/reports/sales?date_from='.now()->toDateString().'&date_to='.now()->addDays(7)->toDateString())
            ->assertOk();
    }
}
