<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\TravelBookingExpirationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-418 (#6070) — job d'expiration des réservations pending.
 *
 * Verrouille : un pending expiré est annulé et libère EXACTEMENT ses sièges
 * (+ événement `travel.booking.expired.v1`) ; un pending non échu n'est pas
 * touché ; un rejeu du job est idempotent (aucun double traitement).
 */
class TravelBookingExpirationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'CM',
            'currency' => 'XAF',
            'features' => ['travelagency' => true],
        ]);
        $this->companyA = $companyA;
    }

    public function test_expired_pending_booking_is_cancelled_and_seats_released(): void
    {
        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->subMinute(),
            ]);

            TravelTripSeat::factory()->count(2)->create([
                'trip_id' => $booking->trip_id,
                'booking_id' => $booking->id,
                'status' => SeatStatus::RESERVED->value,
                'reserved_until' => now()->addMinutes(15),
            ]);

            $count = app(TravelBookingExpirationService::class)->expireDue();

            $this->assertSame(1, $count);

            $booking->refresh();
            $this->assertSame(BookingStatus::CANCELLED, $booking->status);
            $this->assertNull($booking->expires_at);

            // Les 2 sièges sont libérés (free, réservation effacée).
            $this->assertSame(2, TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('booking_id', $booking->id)
                ->where('status', SeatStatus::FREE->value)
                ->count());

            // Événement versionné publié.
            $this->assertSame(1, TravelOutboxEvent::query()
                ->where('event_type', TravelBookingExpirationService::EVENT_BOOKING_EXPIRED)
                ->count());
        });
    }

    public function test_pending_not_due_is_untouched(): void
    {
        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->addMinutes(15),
            ]);

            $count = app(TravelBookingExpirationService::class)->expireDue();

            $this->assertSame(0, $count);
            $this->assertSame(BookingStatus::PENDING, $booking->refresh()->status);
            $this->assertSame(0, TravelOutboxEvent::query()->count());
        });
    }

    public function test_rerun_is_idempotent(): void
    {
        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->subMinute(),
            ]);

            $service = app(TravelBookingExpirationService::class);

            $this->assertSame(1, $service->expireDue());
            // Rejeu : la réservation n'est plus pending → rien à faire.
            $this->assertSame(0, $service->expireDue());

            $this->assertSame(BookingStatus::CANCELLED, $booking->refresh()->status);
            // Un seul événement, jamais deux.
            $this->assertSame(1, TravelOutboxEvent::query()
                ->where('event_type', TravelBookingExpirationService::EVENT_BOOKING_EXPIRED)
                ->count());
        });
    }
}
