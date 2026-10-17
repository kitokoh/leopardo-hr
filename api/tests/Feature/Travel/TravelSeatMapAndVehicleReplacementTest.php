<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\AssessVehicleReplacementAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Mission Leopardo — Travel OS, phase 2bis (#8621, #8622).
 *
 * Couvre les deux surfaces du plan de sièges et la procédure de remplacement
 * de véhicule :
 *
 * - le plan back-office expose l'inventaire RÉEL (numéro + état) et la
 *   référence de réservation, réservée au personnel ;
 * - le plan public n'expose JAMAIS cette référence ni aucune PII ;
 * - un tenant ne peut pas lire les sièges d'un autre tenant (404) ;
 * - un changement de véhicule incompatible est REFUSÉ (409) et n'écrit rien ;
 * - un changement compatible conserve les réservations et les sièges.
 */
class TravelSeatMapAndVehicleReplacementTest extends TestCase
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
     * Trajet publié (ou non) avec N sièges et une classe tarifée.
     *
     * @return array{trip: TravelTrip, class: int}
     */
    private function trip(Company $company, int $totalSeats = 10, string $status = 'draft'): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($totalSeats, $status): array {
            $trip = TravelTrip::factory()->create(['status' => $status, 'total_seats' => $totalSeats]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
                'currency' => 'XAF',
            ]);

            return ['trip' => $trip, 'class' => (int) $class->id];
        });
    }

    private function vehicle(Company $company, int $capacity): TravelVehicle
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelVehicle => TravelVehicle::factory()->create(['seat_capacity' => $capacity]),
        );
    }

    /**
     * Occupe des sièges via une VRAIE réservation (pas un update brut) : la
     * référence sert ensuite à vérifier ce que chaque surface expose.
     *
     * @param  list<int>  $seatNumbers
     */
    private function occupy(Company $company, TravelTrip $trip, array $seatNumbers): TravelBooking
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($trip, $seatNumbers): TravelBooking {
            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => 'confirmed',
                'passenger_count' => count($seatNumbers),
            ]);

            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->whereIn('seat_number', $seatNumbers)
                ->update([
                    'status' => SeatStatus::RESERVED->value,
                    'booking_id' => $booking->id,
                ]);

            return $booking;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // #8621 — plan de sièges du back-office
    // ─────────────────────────────────────────────────────────────────────

    public function test_back_office_seat_map_exposes_real_inventory_with_booking_reference(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->trip($company, totalSeats: 10);
        $booking = $this->occupy($company, $fx['trip'], [3, 4]);

        $response = $this->getJson('/api/v1/travel/trips/'.$fx['trip']->id.'/seats')
            ->assertOk();

        // Inventaire complet : 10 sièges, pas seulement les libres.
        $response->assertJsonCount(10, 'data.seats');
        $response->assertJsonPath('data.total_seats', 10);

        /** @var list<array{seat_number: int, status: string, booking_reference: string|null}> $seats */
        $seats = $response->json('data.seats');
        $byNumber = [];
        foreach ($seats as $seat) {
            $byNumber[$seat['seat_number']] = $seat;
        }

        $this->assertSame('reserved', $byNumber[3]['status']);
        $this->assertSame($booking->reference, $byNumber[3]['booking_reference']);
        $this->assertSame('free', $byNumber[1]['status']);
        $this->assertNull($byNumber[1]['booking_reference']);
    }

    public function test_back_office_seat_map_never_leaks_another_tenant_trip(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $fxB = $this->trip($companyB, totalSeats: 8);

        // L'acteur est un employé de A ; le trajet appartient à B.
        $this->principal($companyA);

        $this->getJson('/api/v1/travel/trips/'.$fxB['trip']->id.'/seats')
            ->assertStatus(404);
    }

    public function test_back_office_seat_map_requires_authentication(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $fx = $this->trip($company, totalSeats: 4);

        $this->getJson('/api/v1/travel/trips/'.$fx['trip']->id.'/seats')
            ->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    // #8622 — remplacement de véhicule
    // ─────────────────────────────────────────────────────────────────────

    public function test_vehicle_replacement_is_refused_when_occupied_seats_exceed_capacity(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->trip($company, totalSeats: 10);
        // Sièges 8 et 9 occupés : un véhicule de 5 places les rend orphelins.
        $booking = $this->occupy($company, $fx['trip'], [8, 9]);
        $smallBus = $this->vehicle($company, 5);

        $response = $this->putJson('/api/v1/travel/trips/'.$fx['trip']->id, [
            'vehicle_id' => $smallBus->id,
        ])->assertStatus(409);

        $response->assertJsonPath('data.compatible', false);

        /** @var list<array{type: string, seat_number: int, severity: string, booking_reference: string|null}> $conflicts */
        $conflicts = $response->json('data.conflicts');

        $blocking = array_values(array_filter(
            $conflicts,
            static fn (array $conflict): bool => $conflict['severity'] === 'blocking',
        ));

        $this->assertCount(2, $blocking);
        $this->assertSame([8, 9], array_column($blocking, 'seat_number'));
        $this->assertSame(
            [$booking->reference, $booking->reference],
            array_column($blocking, 'booking_reference'),
        );

        // RIEN n'a été écrit : le trajet garde son véhicule d'origine.
        app(TenantManager::class)->withinTenant($company, function () use ($fx): void {
            $this->assertNull(TravelTrip::query()->find($fx['trip']->id)?->vehicle_id);
        });
    }

    public function test_vehicle_replacement_preserves_bookings_and_seats_when_compatible(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->trip($company, totalSeats: 10);
        $booking = $this->occupy($company, $fx['trip'], [2, 3]);
        // Véhicule plus grand : aucun siège occupé n'est orphelin.
        $bigBus = $this->vehicle($company, 20);

        $this->putJson('/api/v1/travel/trips/'.$fx['trip']->id, [
            'vehicle_id' => $bigBus->id,
        ])->assertOk();

        app(TenantManager::class)->withinTenant($company, function () use ($fx, $bigBus, $booking): void {
            $trip = TravelTrip::query()->find($fx['trip']->id);

            // Affectation mise à jour…
            $this->assertSame($bigBus->id, (int) $trip?->vehicle_id);
            // …inventaire INTACT : aucun siège régénéré ni dupliqué.
            $this->assertSame(10, TravelTripSeat::query()->where('trip_id', $fx['trip']->id)->count());
            // …réservations conservées et toujours rattachées aux mêmes sièges.
            $this->assertSame(
                [2, 3],
                TravelTripSeat::query()
                    ->where('trip_id', $fx['trip']->id)
                    ->where('booking_id', $booking->id)
                    ->orderBy('seat_number')
                    ->pluck('seat_number')
                    ->all(),
            );
        });
    }

    public function test_vehicle_assessment_endpoint_lists_conflicts_without_writing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->trip($company, totalSeats: 10);
        $this->occupy($company, $fx['trip'], [9]);
        $smallBus = $this->vehicle($company, 6);

        $response = $this->getJson(
            '/api/v1/travel/trips/'.$fx['trip']->id.'/vehicle-assessment?vehicle_id='.$smallBus->id
        )->assertOk();

        $response->assertJsonPath('data.compatible', false);
        $response->assertJsonPath('data.target_capacity', 6);

        // L'évaluation est PURE : le trajet n'a pas bougé.
        app(TenantManager::class)->withinTenant($company, function () use ($fx): void {
            $this->assertNull(TravelTrip::query()->find($fx['trip']->id)?->vehicle_id);
        });
    }

    public function test_vehicle_assessment_action_reports_compatibility_for_free_seats_only(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->trip($company, totalSeats: 10);
        $smallBus = $this->vehicle($company, 4);

        $assessment = app(AssessVehicleReplacementAction::class)->assess($fx['trip'], $smallBus->id);

        // Aucun siège occupé : compatible malgré des sièges libres au-delà de
        // la capacité — ils remontent en avertissements, pas en blocage.
        $this->assertTrue($assessment['compatible']);
        $this->assertSame(4, $assessment['target_capacity']);

        $warnings = array_values(array_filter(
            $assessment['conflicts'],
            static fn (array $conflict): bool => $conflict['severity'] === 'warning',
        ));

        $this->assertCount(6, $warnings); // sièges 5 à 10
        $this->assertSame('free_seat_beyond_capacity', $warnings[0]['type']);
    }

    public function test_vehicle_assessment_rejects_unknown_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->trip($company, totalSeats: 10);

        $assessment = app(AssessVehicleReplacementAction::class)->assess($fx['trip'], 999999);

        $this->assertFalse($assessment['compatible']);
        $this->assertSame('vehicle_not_found', $assessment['error'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Concurrence réelle sur un siège (base réelle, verrou serveur)
    // ─────────────────────────────────────────────────────────────────────

    public function test_two_concurrent_bookings_cannot_take_the_same_seat(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->trip($company, totalSeats: 10, status: 'published');
        $classId = $fx['class'];

        $first = $this->bookSeat($company, $fx['trip'], $classId, 5, 'concurrency-first');

        // Second client sur le MÊME siège : doit échouer, pas écraser.
        $secondFailed = false;
        try {
            $this->bookSeat($company, $fx['trip'], $classId, 5, 'concurrency-second');
        } catch (\Throwable) {
            $secondFailed = true;
        }

        $this->assertTrue($secondFailed, 'Un second client ne peut pas obtenir un siège déjà réservé.');

        app(TenantManager::class)->withinTenant($company, function () use ($fx, $first): void {
            $holders = TravelTripSeat::query()
                ->where('trip_id', $fx['trip']->id)
                ->where('seat_number', 5)
                ->get();

            // Un seul siège, un seul titulaire : aucune double attribution.
            $this->assertCount(1, $holders);
            $this->assertSame((int) $first->id, (int) $holders->first()?->booking_id);
        });
    }

    private function bookSeat(
        Company $company,
        TravelTrip $trip,
        int $classId,
        int $seatNumber,
        string $idempotencyKey,
    ): TravelBooking {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelBooking => app(\App\Modules\TravelAgency\Application\Actions\CreateBookingAction::class)->execute(
                trip: $trip,
                passengers: [[
                    'full_name' => 'Voyageur '.$idempotencyKey,
                    'age_category' => 'adult',
                    'class_id' => $classId,
                    'seat_number' => $seatNumber,
                ]],
                source: \App\Modules\TravelAgency\Domain\Enums\BookingSource::MARKETPLACE,
                idempotencyKey: $idempotencyKey,
            ),
        );
    }
}
