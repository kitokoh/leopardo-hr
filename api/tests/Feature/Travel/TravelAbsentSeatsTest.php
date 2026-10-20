<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\ValueObjects\SeatLayout;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Mission Leopardo — Travel OS (#8652, #8653).
 *
 * Verrouille les sièges PHYSIQUEMENT ABSENTS et la cohérence
 * disposition ↔ inventaire :
 *
 * - un siège absent n'est JAMAIS créé dans l'inventaire du trajet (l'inventaire
 *   correspond au bus réel, pas au nombre de places déclaré) ;
 * - `sellableCapacity()` est la capacité réellement commercialisable ;
 * - une disposition incohérente est REFUSÉE explicitement (422), jamais
 *   corrigée silencieusement ;
 * - rendre absent un siège déjà réservé est refusé, avec la liste des conflits ;
 * - un véhicule non configuré reste strictement inchangé (rétrocompatibilité).
 */
class TravelAbsentSeatsTest extends TestCase
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

    // ── Value object ────────────────────────────────────────────────────────

    public function test_sellable_capacity_soustrait_les_sieges_absents(): void
    {
        $layout = SeatLayout::tryFromArray([
            'seats_per_row' => 4,
            'aisle_after' => 2,
            'absent_seats' => [29, 30],
        ], 30);

        $this->assertNotNull($layout);
        $this->assertSame([29, 30], $layout->absentSeats);
        $this->assertSame(28, $layout->sellableCapacity(30));
    }

    public function test_un_siege_absent_hors_capacite_rend_la_disposition_inexploitable(): void
    {
        // 31 > capacité 30 : on ne devine pas, on refuse la disposition.
        $this->assertNull(SeatLayout::tryFromArray([
            'seats_per_row' => 4,
            'aisle_after' => 2,
            'absent_seats' => [31],
        ], 30));
    }

    public function test_une_forme_absent_seats_inexploitable_rend_la_disposition_inexploitable(): void
    {
        $this->assertNull(SeatLayout::tryFromArray([
            'seats_per_row' => 4,
            'aisle_after' => 2,
            'absent_seats' => 'pas-un-tableau',
        ], 30));
    }

    public function test_absent_seats_absent_de_la_charge_utile_quand_il_est_vide(): void
    {
        // Rétrocompatibilité de contrat : un véhicule sans siège absent garde
        // exactement la charge utile d'avant #8652.
        $layout = SeatLayout::tryFromArray(['seats_per_row' => 4, 'aisle_after' => 2], 30);

        $this->assertNotNull($layout);
        $this->assertSame(['seats_per_row' => 4, 'aisle_after' => 2], $layout->toArray());
    }

    // ── Inventaire ──────────────────────────────────────────────────────────

    public function test_l_inventaire_ne_cree_aucun_siege_physiquement_absent(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => 30,
                'seat_layout' => [
                    'seats_per_row' => 4,
                    'aisle_after' => 2,
                    'absent_seats' => [29, 30],
                ],
            ]);

            $trip = TravelTrip::factory()->create([
                'status' => 'draft',
                'total_seats' => 30,
                'vehicle_id' => $vehicle->id,
            ]);

            app(GenerateTripSeatsAction::class)->execute($trip);

            $numbers = TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->orderBy('seat_number')
                ->pluck('seat_number')
                ->all();

            // 28 sièges vendables : 1..28, ni 29 ni 30.
            $this->assertCount(28, $numbers);
            $this->assertSame(range(1, 28), $numbers);
            $this->assertNotContains(29, $numbers);
            $this->assertNotContains(30, $numbers);
        });
    }

    public function test_un_vehicule_non_configure_cree_toujours_toutes_les_places(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => 30,
                'seat_layout' => null,
            ]);

            $trip = TravelTrip::factory()->create([
                'status' => 'draft',
                'total_seats' => 30,
                'vehicle_id' => $vehicle->id,
            ]);

            app(GenerateTripSeatsAction::class)->execute($trip);

            $this->assertSame(
                30,
                TravelTripSeat::query()->where('trip_id', $trip->id)->count(),
            );
        });
    }

    // ── Validation API ──────────────────────────────────────────────────────

    public function test_la_creation_refuse_un_siege_absent_hors_capacite(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'VEH-ABSENT-OOR',
            'seat_capacity' => 30,
            'seat_layout' => [
                'seats_per_row' => 4,
                'aisle_after' => 2,
                'absent_seats' => [31],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'absent_seat_out_of_range');
    }

    public function test_la_creation_refuse_un_siege_absent_duplique(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'VEH-ABSENT-DUP',
            'seat_capacity' => 30,
            'seat_layout' => [
                'seats_per_row' => 4,
                'aisle_after' => 2,
                'absent_seats' => [29, 29],
            ],
        ]);

        // Le FormRequest attrape le doublon avant l'action : 422 dans tous les cas.
        $response->assertStatus(422);
    }

    public function test_la_creation_accepte_une_disposition_coherente(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'VEH-ABSENT-OK',
            'seat_capacity' => 30,
            'seat_layout' => [
                'seats_per_row' => 4,
                'aisle_after' => 2,
                'absent_seats' => [29, 30],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.seat_layout.absent_seats', [29, 30]);
    }

    public function test_la_mise_a_jour_refuse_de_rendre_absent_un_siege_deja_reserve(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => 30,
                'seat_layout' => ['seats_per_row' => 4, 'aisle_after' => 2],
            ]);

            $trip = TravelTrip::factory()->create([
                'status' => 'draft',
                'total_seats' => 30,
                'vehicle_id' => $vehicle->id,
            ]);

            app(GenerateTripSeatsAction::class)->execute($trip);

            // Le siège 30 est attribué à une réservation.
            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('seat_number', 30)
                ->update(['booking_id' => 999999]);

            return $vehicle->id;
        });

        $response = $this->putJson("/api/v1/travel/vehicles/{$vehicleId}", [
            'seat_layout' => [
                'seats_per_row' => 4,
                'aisle_after' => 2,
                'absent_seats' => [30],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'layout_conflicts_with_existing_seats');
        $response->assertJsonPath('details.seats', [30]);
    }

    public function test_la_mise_a_jour_accepte_un_siege_absent_libre(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            $vehicle = TravelVehicle::factory()->create([
                'seat_capacity' => 30,
                'seat_layout' => ['seats_per_row' => 4, 'aisle_after' => 2],
            ]);

            $trip = TravelTrip::factory()->create([
                'status' => 'draft',
                'total_seats' => 30,
                'vehicle_id' => $vehicle->id,
            ]);

            app(GenerateTripSeatsAction::class)->execute($trip);

            return $vehicle->id;
        });

        $response = $this->putJson("/api/v1/travel/vehicles/{$vehicleId}", [
            'seat_layout' => [
                'seats_per_row' => 4,
                'aisle_after' => 2,
                'absent_seats' => [30],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.seat_layout.absent_seats', [30]);
    }

    public function test_un_vehicule_non_configure_reste_accepte_sans_geometrie(): void
    {
        $company = Company::factory()->create();
        $this->activateTravel($company);
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/vehicles', [
            'code' => 'VEH-NO-LAYOUT',
            'seat_capacity' => 30,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.seat_layout', null);
    }
}
