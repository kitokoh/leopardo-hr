<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\ValueObjects\SeatLayout;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Mission Leopardo — Travel OS (#8644, #8645, #8646).
 *
 * Verrouille la disposition PHYSIQUE des sièges :
 *
 * - un véhicule non configuré retombe sur le repli par capacité, et l'API le
 *   dit (`is_configured = false`) au lieu de faire passer une déduction pour
 *   une donnée lue ;
 * - un véhicule configuré expose exactement sa géométrie ;
 * - la validation refuse les dispositions impossibles (couloir après la
 *   dernière colonne, trop de colonnes) ;
 * - les surfaces publique et back-office lisent la MÊME source de vérité ;
 * - un tenant ne peut pas lire la disposition d'un trajet d'un autre tenant.
 */
class TravelSeatLayoutTest extends TestCase
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
     * @param  array<string, mixed>|null  $layout
     * @return array{trip: TravelTrip, vehicle: TravelVehicle}
     */
    private function tripWithVehicle(
        Company $company,
        int $totalSeats,
        ?array $layout,
        string $status = 'draft',
    ): array {
        return app(TenantManager::class)->withinTenant($company, function () use ($totalSeats, $layout, $status): array {
            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => $totalSeats,
                'seat_layout' => $layout,
            ]);

            $trip = TravelTrip::factory()->create([
                'status' => $status,
                'total_seats' => $totalSeats,
                'vehicle_id' => $vehicle->id,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
                'currency' => 'XAF',
            ]);

            return ['trip' => $trip, 'vehicle' => $vehicle];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Value object + modèle
    // ─────────────────────────────────────────────────────────────────────────

    public function test_value_object_rejects_impossible_layouts(): void
    {
        // 7 colonnes : au-delà de la borne haute.
        $this->assertNull(SeatLayout::tryFromArray(['seats_per_row' => 7]));

        // Couloir après la dernière colonne : impossible.
        $this->assertNull(SeatLayout::tryFromArray(['seats_per_row' => 3, 'aisle_after' => 3]));

        // Couloir avant la première colonne : impossible.
        $this->assertNull(SeatLayout::tryFromArray(['seats_per_row' => 3, 'aisle_after' => 0]));

        // Forme valide : 2+1.
        $valid = SeatLayout::tryFromArray(['seats_per_row' => 3, 'aisle_after' => 1]);
        $this->assertNotNull($valid);
        $this->assertSame(['seats_per_row' => 3, 'aisle_after' => 1], $valid->toArray());
    }

    public function test_unconfigured_vehicle_falls_back_to_capacity_and_says_so(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            // 40 places → repli 2+2 (4 colonnes, couloir après la 2e).
            $big = TravelVehicle::factory()->create(['seat_capacity' => 40, 'seat_layout' => null]);
            $this->assertSame(
                ['seats_per_row' => 4, 'aisle_after' => 2, 'is_configured' => false],
                $big->effectiveSeatLayout(),
            );

            // 12 places → repli 2+1 (3 colonnes, couloir après la 2e).
            $small = TravelVehicle::factory()->create(['seat_capacity' => 12, 'seat_layout' => null]);
            $this->assertSame(
                ['seats_per_row' => 3, 'aisle_after' => 2, 'is_configured' => false],
                $small->effectiveSeatLayout(),
            );

            // Une disposition configurée PRIME et est annoncée comme lue.
            $configured = TravelVehicle::factory()->create([
                'seat_capacity' => 30,
                'seat_layout' => ['seats_per_row' => 3, 'aisle_after' => 1],
            ]);
            $this->assertSame(
                ['seats_per_row' => 3, 'aisle_after' => 1, 'is_configured' => true],
                $configured->effectiveSeatLayout(),
            );
        });
    }

    public function test_corrupt_stored_layout_never_breaks_the_seat_map(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => 40,
                // Forme inexploitable : elle ne doit jamais faire échouer le plan.
                'seat_layout' => ['seats_per_row' => 'beaucoup'],
            ]);
            $this->assertNull($vehicle->seatLayout());

            $effective = $vehicle->effectiveSeatLayout();
            $this->assertSame(4, $effective['seats_per_row']);
            $this->assertFalse($effective['is_configured']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation à l'écriture
    // ─────────────────────────────────────────────────────────────────────────

    public function test_vehicle_creation_accepts_a_valid_layout(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-2PLUS1',
            'seat_capacity' => 30,
            'seat_layout' => ['seats_per_row' => 3, 'aisle_after' => 1],
        ])->assertStatus(201);

        $response->assertJsonPath('data.seat_layout.seats_per_row', 3);
        $response->assertJsonPath('data.seat_layout.aisle_after', 1);
        $response->assertJsonPath('data.effective_seat_layout.is_configured', true);
    }

    public function test_vehicle_creation_rejects_an_impossible_layout(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // 7 colonnes.
        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-7COL',
            'seat_capacity' => 42,
            'seat_layout' => ['seats_per_row' => 7],
        ])->assertStatus(422)->assertJsonValidationErrors('seat_layout.seats_per_row');

        // Couloir après la dernière colonne.
        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-AISLE-END',
            'seat_capacity' => 30,
            'seat_layout' => ['seats_per_row' => 3, 'aisle_after' => 3],
        ])->assertStatus(422)->assertJsonValidationErrors('seat_layout.aisle_after');
    }

    public function test_vehicle_without_layout_is_accepted_and_reports_fallback(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-LEGACY',
            'seat_capacity' => 40,
        ])->assertStatus(201);

        $response->assertJsonPath('data.seat_layout', null);
        $response->assertJsonPath('data.effective_seat_layout.seats_per_row', 4);
        $response->assertJsonPath('data.effective_seat_layout.is_configured', false);
    }

    public function test_vehicle_seat_capacity_out_of_bounds_still_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'BUS-ZERO',
            'seat_capacity' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('seat_capacity');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exposition des deux surfaces (#8645)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_back_office_seat_endpoint_exposes_the_configured_layout(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->tripWithVehicle($company, 30, ['seats_per_row' => 3, 'aisle_after' => 1]);

        $this->getJson('/api/v1/travel/trips/'.$fx['trip']->id.'/seats')
            ->assertOk()
            ->assertJsonPath('data.seat_layout.seats_per_row', 3)
            ->assertJsonPath('data.seat_layout.aisle_after', 1)
            ->assertJsonPath('data.seat_layout.is_configured', true);
    }

    public function test_back_office_seat_endpoint_falls_back_when_vehicle_unconfigured(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->tripWithVehicle($company, 40, null);

        $this->getJson('/api/v1/travel/trips/'.$fx['trip']->id.'/seats')
            ->assertOk()
            ->assertJsonPath('data.seat_layout.seats_per_row', 4)
            ->assertJsonPath('data.seat_layout.is_configured', false);
    }

    public function test_back_office_seat_layout_is_tenant_isolated(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $fxB = $this->tripWithVehicle($companyB, 30, ['seats_per_row' => 3, 'aisle_after' => 1]);

        // Acteur de A, trajet de B.
        $this->principal($companyA);

        $this->getJson('/api/v1/travel/trips/'.$fxB['trip']->id.'/seats')
            ->assertStatus(404);
    }

    public function test_public_marketplace_exposes_the_same_layout_without_pii(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['name' => 'Agence Layout', 'country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        TravelPublicShopToken::query()->create([
            'company_id' => $company->id,
            'token_hash' => TravelPublicShopToken::hash('tshop_layout_'.random_int(1000, 9999)),
            'name' => 'Boutique Layout',
            'active' => true,
        ]);

        $fx = app(TenantManager::class)->withinTenant($company, function (): array {
            $origin = TravelCity::factory()->create(['name' => 'Douala', 'country_iso2' => 'CM']);
            $destination = TravelCity::factory()->create(['name' => 'Yaoundé', 'country_iso2' => 'CM']);
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);

            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => 30,
                'seat_layout' => ['seats_per_row' => 3, 'aisle_after' => 1],
            ]);

            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 30,
                'vehicle_id' => $vehicle->id,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'currency' => 'XAF',
            ]);

            return ['trip' => $trip];
        });

        $response = $this->getJson('/api/v1/public/travel/marketplace/trips/'.$fx['trip']->id)
            ->assertOk();

        $response->assertJsonPath('data.seat_layout.seats_per_row', 3);
        $response->assertJsonPath('data.seat_layout.aisle_after', 1);
        $response->assertJsonPath('data.seat_layout.is_configured', true);

        // Le plan public ne divulgue jamais de référence de réservation.
        $seats = $response->json('data.seats');
        $this->assertIsArray($seats);
        foreach ($seats as $seat) {
            $this->assertArrayNotHasKey('booking_reference', $seat);
        }

        // Ni identifiant tenant, ni référence de réservation.
        $raw = (string) $response->getContent();
        $this->assertStringNotContainsString((string) $company->id, $raw);
        $this->assertStringNotContainsString('booking_reference', $raw);
    }

    public function test_public_marketplace_falls_back_when_trip_has_no_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['name' => 'Agence Sans Bus', 'country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        TravelPublicShopToken::query()->create([
            'company_id' => $company->id,
            'token_hash' => TravelPublicShopToken::hash('tshop_noveh_'.random_int(1000, 9999)),
            'name' => 'Boutique Sans Bus',
            'active' => true,
        ]);

        $fx = app(TenantManager::class)->withinTenant($company, function (): array {
            $origin = TravelCity::factory()->create(['name' => 'Kribi', 'country_iso2' => 'CM']);
            $destination = TravelCity::factory()->create(['name' => 'Ebolowa', 'country_iso2' => 'CM']);
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);

            // Aucun véhicule affecté — cas explicitement supporté.
            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 12,
                'vehicle_id' => null,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 8000,
                'currency' => 'XAF',
            ]);

            return ['trip' => $trip];
        });

        $this->getJson('/api/v1/public/travel/marketplace/trips/'.$fx['trip']->id)
            ->assertOk()
            ->assertJsonPath('data.seat_layout.seats_per_row', 3)
            ->assertJsonPath('data.seat_layout.is_configured', false);
    }
}
