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
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-313..317 (#6043..#6047) — Workflow réservation : confirm → tickets
 * → check-in, annulation, remboursement.
 *
 * Couvre les transitions d'état, la libération/vente des sièges, les
 * événements outbox et les gardes d'état.
 */
class TravelBookingWorkflowTest extends TestCase
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
     * Crée une réservation pending confirmable via l'API.
     *
     * @return array{booking: TravelBooking, trip: TravelTrip}
     */
    private function createPendingBooking(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::PENDING,
                'passenger_count' => 1,
                'total_amount_minor' => 15000,
            ]);

            $passenger = $booking->passengers()->create([
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'seat_number' => 1,
                'unit_price_minor' => 15000,
            ]);

            // Réserve un siège comme le ferait CreateBookingAction.
            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('status', SeatStatus::FREE)
                ->first()
                ?->forceFill([
                    'status' => SeatStatus::RESERVED,
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                    'reserved_until' => now()->addMinutes(15),
                ])->save();

            return ['booking' => $booking, 'trip' => $trip];
        });
    }

    public function test_confirm_marks_seats_sold_and_publishes_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->createPendingBooking($company);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function () use ($booking): int {
            return TravelTripSeat::query()
                ->where('booking_id', $booking->id)
                ->where('status', SeatStatus::SOLD)
                ->count();
        }));

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOutboxEvent::query()
                ->where('event_type', 'travel.booking.confirmed.v1')
                ->count();
        }));
    }

    public function test_issue_tickets_creates_one_per_passenger(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->createPendingBooking($company);
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/issue-ticket")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'issued');

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTicket::query()->count();
        }));
    }

    public function test_cancel_releases_seats_and_requires_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->createPendingBooking($company);

        // Motif obligatoire.
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/cancel")->assertStatus(422);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/cancel", ['reason' => 'Client désisté'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertSame(0, app(TenantManager::class)->withinTenant($company, function () use ($booking): int {
            return TravelTripSeat::query()
                ->where('booking_id', $booking->id)
                ->where('status', SeatStatus::RESERVED)
                ->count();
        }));
    }

    public function test_refund_requires_confirmed_booking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->createPendingBooking($company);

        // Une réservation pending ne peut pas être remboursée.
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", ['reason' => 'Test'])
            ->assertStatus(422);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/refund", ['reason' => 'Annulation client'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::REFUNDED->value);
    }

    public function test_check_in_requires_issued_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = app(TenantManager::class)->withinTenant($company, function (): TravelTicket {
            $booking = TravelBooking::factory()->create(['status' => BookingStatus::CONFIRMED]);
            $passenger = $booking->passengers()->create([
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => TravelClass::factory()->create()->id,
                'unit_price_minor' => 15000,
            ]);

            return TravelTicket::factory()->create(['booking_id' => $booking->id, 'passenger_id' => $passenger->id]);
        });

        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.checked_in_at', fn ($value) => $value !== null);
    }

    public function test_manifest_lists_passengers_by_seat(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'trip' => $trip] = $this->createPendingBooking($company);

        $this->getJson("/api/v1/travel/trips/{$trip->id}/manifest")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
