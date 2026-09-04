<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-812 (#6102) — Annulation d'un trajet par l'agence.
 *
 * Couvre la cascade : trajet → cancelled, réservations confirmées →
 * refunded (remboursement intégral, pénalités neutralisées), réservations
 * pending → cancelled, sièges libérés, événements outbox pour les
 * notifications voyageurs, et l'idempotence du rejeu.
 */
class TravelTripCancellationApiTest extends TestCase
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
     * Trajet publié avec 1 réservation confirmée + 1 pending.
     *
     * @return array{trip: TravelTrip, confirmed: TravelBooking, pending: TravelBooking}
     */
    private function publishedTripWithBookings(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            $book = function (BookingStatus $status, int $seatOffset) use ($trip, $class): TravelBooking {
                $booking = TravelBooking::factory()->create([
                    'trip_id' => $trip->id,
                    'status' => $status,
                    'payment_status' => $status === BookingStatus::CONFIRMED ? 'confirmed' : 'pending',
                    'passenger_count' => 1,
                    'total_amount_minor' => 10000,
                ]);

                $seat = TravelTripSeat::query()
                    ->where('trip_id', $trip->id)
                    ->where('status', SeatStatus::FREE)
                    ->orderBy('seat_number')
                    ->skip($seatOffset)
                    ->first();

                $passenger = $booking->passengers()->create([
                    'full_name' => 'Passager '.$seatOffset,
                    'age_category' => 'adult',
                    'class_id' => $class->id,
                    'seat_number' => $seat?->seat_number,
                    'unit_price_minor' => 10000,
                ]);

                $seat?->forceFill([
                    'status' => $status === BookingStatus::CONFIRMED ? SeatStatus::SOLD : SeatStatus::RESERVED,
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                    'reserved_until' => now()->addMinutes(15),
                ])->save();

                return $booking;
            };

            return [
                'trip' => $trip,
                'confirmed' => $book(BookingStatus::CONFIRMED, 0),
                'pending' => $book(BookingStatus::PENDING, 1),
            ];
        });
    }

    public function test_cancel_trip_refunds_confirmed_and_cancels_pending(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip, 'confirmed' => $confirmed, 'pending' => $pending] = $this->publishedTripWithBookings($company);

        $this->postJson("/api/v1/travel/trips/{$trip->id}/cancel", [
            'reason' => 'Incident matériel — trajet annulé par l\'agence',
        ])->assertOk()
            ->assertJsonPath('data.status', TripStatus::CANCELLED->value);

        $this->assertSame(TripStatus::CANCELLED, $trip->refresh()->status);
        $this->assertSame(BookingStatus::REFUNDED, $confirmed->refresh()->status);
        $this->assertSame(BookingStatus::CANCELLED, $pending->refresh()->status);

        // Tous les sièges libérés.
        $this->assertSame(0, TravelTripSeat::query()
            ->where('trip_id', $trip->id)
            ->where('status', '!=', SeatStatus::FREE)
            ->count());

        // Événements : trip.cancelled + refunds + cancellations.
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.trip.cancelled.v1')
            ->count());
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.payment.refunded.v1')
            ->count());
        $this->assertGreaterThanOrEqual(2, TravelOutboxEvent::query()
            ->where('event_type', 'travel.booking.cancelled.v1')
            ->count());
    }

    public function test_cancel_trip_replay_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip] = $this->publishedTripWithBookings($company);

        $payload = ['reason' => 'Incident matériel'];

        $this->postJson("/api/v1/travel/trips/{$trip->id}/cancel", $payload)->assertOk();
        $this->postJson("/api/v1/travel/trips/{$trip->id}/cancel", $payload)->assertOk();

        // Aucun doublon d'événement de remboursement au rejeu.
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.payment.refunded.v1')
            ->count());
    }

    public function test_cancel_trip_requires_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['trip' => $trip] = $this->publishedTripWithBookings($company);

        $this->postJson("/api/v1/travel/trips/{$trip->id}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }
}
