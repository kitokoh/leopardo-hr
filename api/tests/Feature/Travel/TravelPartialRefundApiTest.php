<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-808 (#6098) — Remboursements partiels par passager.
 *
 * Couvre : remboursement d'un passager sur deux (réservation reste
 * confirmée), pénalité calculée serveur (politique TRAVEL-813), siège
 * libéré, idempotence (pas de double remboursement au rejeu),
 * remboursement complet quand tous les passagers sont sélectionnés,
 * rejet d'un passager étranger (404) et d'un passager non remboursable
 * (422).
 */
class TravelPartialRefundApiTest extends TestCase
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
     * Réservation confirmée à 2 passagers (2 sièges réservés puis sold).
     *
     * @return array{booking: TravelBooking, passengers: array<int, int>}
     */
    private function confirmedBookingWithTwoPassengers(Company $company, ?int $penaltyPercent = null): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($penaltyPercent): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            if ($penaltyPercent !== null) {
                TravelCancellationPolicy::factory()->global($penaltyPercent)->create();
            }

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED,
                'payment_status' => 'confirmed',
                'passenger_count' => 2,
                'total_amount_minor' => 20000,
            ]);

            $passengerIds = [];
            $seats = TravelTripSeat::query()->where('trip_id', $trip->id)
                ->where('status', SeatStatus::FREE)
                ->orderBy('seat_number')
                ->limit(2)
                ->get();

            foreach ($seats as $index => $seat) {
                $passenger = $booking->passengers()->create([
                    'full_name' => 'Passager '.($index + 1),
                    'age_category' => 'adult',
                    'class_id' => $class->id,
                    'seat_number' => $seat->seat_number,
                    'unit_price_minor' => 10000,
                ]);

                $seat->forceFill([
                    'status' => SeatStatus::SOLD,
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                ])->save();

                $passengerIds[] = $passenger->id;
            }

            return ['booking' => $booking, 'passengers' => $passengerIds];
        });
    }

    public function test_partial_refund_of_one_passenger_keeps_booking_confirmed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'passengers' => $passengerIds] = $this->confirmedBookingWithTwoPassengers($company);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", [
            'reason' => 'Changement de programme du passager 1',
            'passenger_ids' => [$passengerIds[0]],
        ])->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);

        $passenger = TravelPassenger::query()->findOrFail($passengerIds[0]);
        $this->assertNotNull($passenger->refunded_at);
        $this->assertSame(10000, $passenger->refunded_amount_minor);

        // Le siège du passager remboursé est libéré.
        $this->assertSame(1, TravelTripSeat::query()
            ->where('passenger_id', $passengerIds[0])
            ->where('status', SeatStatus::FREE)
            ->count());

        // Événement partiel publié.
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.payment.refunded.v1')
            ->whereJsonContains('payload_redacted', ['partial' => true])
            ->count());
    }

    public function test_penalty_is_computed_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'passengers' => $passengerIds] = $this->confirmedBookingWithTwoPassengers($company, penaltyPercent: 25);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", [
            'reason' => 'Annulation tardive',
            'passenger_ids' => [$passengerIds[0]],
        ])->assertOk();

        // 10 000 − 25 % = 7 500 remboursés, 2 500 de pénalité.
        $passenger = TravelPassenger::query()->findOrFail($passengerIds[0]);
        $this->assertSame(7500, $passenger->refunded_amount_minor);
    }

    public function test_refund_replay_never_double_refunds(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'passengers' => $passengerIds] = $this->confirmedBookingWithTwoPassengers($company);

        $payload = [
            'reason' => 'Rejeu réseau',
            'passenger_ids' => [$passengerIds[0]],
        ];

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", $payload)->assertOk();
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", $payload)->assertOk();

        $this->assertSame(1, TravelPassenger::query()
            ->where('refunded_at', '!=', null)
            ->count());
    }

    public function test_refund_all_passengers_marks_booking_refunded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'passengers' => $passengerIds] = $this->confirmedBookingWithTwoPassengers($company);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", [
            'reason' => 'Annulation complète',
            'passenger_ids' => $passengerIds,
        ])->assertOk()
            ->assertJsonPath('data.status', BookingStatus::REFUNDED->value);

        $this->assertSame(0, TravelTripSeat::query()
            ->where('booking_id', $booking->id)
            ->where('status', SeatStatus::SOLD)
            ->count());
    }

    public function test_foreign_passenger_id_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->confirmedBookingWithTwoPassengers($company);

        $foreignId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelPassenger::factory()->create()->id;
        });

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", [
            'reason' => 'Mauvais passager',
            'passenger_ids' => [$foreignId],
        ])->assertStatus(404);
    }

    public function test_non_refundable_policy_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'passengers' => $passengerIds] = $this->confirmedBookingWithTwoPassengers($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelCancellationPolicy::factory()->nonRefundable()->create();
        });

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", [
            'reason' => 'Trop tard',
            'passenger_ids' => [$passengerIds[0]],
        ])->assertStatus(422);
    }
}
