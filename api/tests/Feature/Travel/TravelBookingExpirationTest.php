<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExpirePendingBookingsJob;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\Bus;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending.
 *
 * Job tenant-scoped : pending avec `expires_at` dépassé → cancelled, sièges
 * libérés, événement `travel.booking.expired.v1` publié (après commit).
 * Idempotence (rejeu sans doublon), non-expiration avant délai, isolation
 * cross-tenant et dispatch par compagnie via la commande.
 */
class TravelBookingExpirationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    /**
     * Crée une réservation pending (optionnellement expirée) avec un siège réservé.
     */
    private function pendingBooking(Company $company, ?\DateTimeInterface $expiresAt): TravelBooking
    {
        /** @var TravelTrip $trip */
        $trip = TravelTrip::factory()->create();

        /** @var TravelBooking $booking */
        $booking = TravelBooking::query()->create([
            'trip_id' => $trip->id,
            'status' => BookingStatus::PENDING->value,
            'passenger_count' => 1,
            'total_amount_minor' => 100000,
            'currency' => 'XAF',
            'booking_source' => 'office',
            'payment_status' => 'pending',
            'expires_at' => $expiresAt,
            'version' => 1,
        ]);

        TravelTripSeat::query()->create([
            'trip_id' => $booking->trip_id,
            'seat_number' => 1,
            'status' => SeatStatus::RESERVED->value,
            'booking_id' => $booking->id,
            'reserved_until' => now()->addMinutes(15),
        ]);

        return $booking;
    }

    private function runJob(Company $company): void
    {
        $this->tenants->withinTenant($company, function () use ($company): void {
            (new ExpirePendingBookingsJob($company->id))->handle(app(TravelOutboxPublisher::class));
        });
    }

    public function test_expired_pending_booking_is_cancelled_and_seats_freed(): void
    {
        $booking = $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->subMinutes(5)));

        $this->runJob($this->company);

        $this->tenants->withinTenant($this->company, function () use ($booking): void {
            $booking->refresh();

            $this->assertSame(BookingStatus::CANCELLED, $booking->status);
            $this->assertNull($booking->expires_at);
            $this->assertSame(2, $booking->version);

            $seat = TravelTripSeat::query()
                ->where('trip_id', $booking->trip_id)
                ->where('booking_id', $booking->id)
                ->firstOrFail();
            $this->assertSame(SeatStatus::FREE, $seat->status);
            $this->assertNull($seat->reserved_until);

            $event = TravelOutboxEvent::query()
                ->where('event_type', 'travel.booking.expired.v1')
                ->firstOrFail();
            $this->assertSame(TravelOutboxEvent::STATUS_PENDING, $event->status);
            $this->assertSame('booking-expired-'.$booking->id, $event->idempotency_key);
            $this->assertSame($booking->reference, $event->payload_redacted['booking_reference']);
            $this->assertSame('pending_expired', $event->payload_redacted['reason']);
        });
    }

    public function test_not_yet_expired_booking_stays_pending(): void
    {
        $booking = $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->addMinutes(30)));

        $this->runJob($this->company);

        $this->tenants->withinTenant($this->company, function () use ($booking): void {
            $this->assertSame(BookingStatus::PENDING, $booking->refresh()->status);
            $this->assertSame(0, TravelOutboxEvent::query()->where('event_type', 'travel.booking.expired.v1')->count());
        });
    }

    public function test_replay_is_idempotent(): void
    {
        $booking = $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->subMinutes(5)));

        $this->runJob($this->company);
        $this->runJob($this->company);

        $this->tenants->withinTenant($this->company, function () use ($booking): void {
            $this->assertSame(BookingStatus::CANCELLED, $booking->refresh()->status);
            $this->assertSame(2, $booking->version);
            $this->assertSame(1, TravelOutboxEvent::query()->where('event_type', 'travel.booking.expired.v1')->count());
        });
    }

    public function test_job_is_isolated_to_its_company(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $bookingA = $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->subMinutes(5)));
        $bookingB = $this->tenants->withinTenant($otherCompany, fn (): TravelBooking => $this->pendingBooking($otherCompany, now()->subMinutes(5)));

        // Le job ne s'exécute que pour la compagnie A.
        $this->runJob($this->company);

        $this->tenants->withinTenant($otherCompany, function () use ($bookingB): void {
            $this->assertSame(BookingStatus::PENDING, $bookingB->refresh()->status);
            $this->assertSame(0, TravelOutboxEvent::query()->count());
        });

        $this->tenants->withinTenant($this->company, function () use ($bookingA): void {
            $this->assertSame(BookingStatus::CANCELLED, $bookingA->refresh()->status);
        });
    }

    public function test_command_sync_expires_due_bookings(): void
    {
        $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->subMinutes(5)));

        $this->artisan('travel:expire-pending-bookings', ['--sync' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('compagnie(s) concernée(s)');

        $this->tenants->withinTenant($this->company, function (): void {
            $this->assertSame(
                1,
                TravelBooking::query()->where('status', BookingStatus::CANCELLED->value)->count(),
            );
        });
    }

    public function test_command_queues_one_job_per_company_with_due_bookings(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->subMinutes(5)));
        $this->tenants->withinTenant($otherCompany, fn (): TravelBooking => $this->pendingBooking($otherCompany, now()->subMinutes(2)));
        // Pas de réservation due pour une troisième compagnie.
        $this->tenants->withinTenant($this->company, fn (): TravelBooking => $this->pendingBooking($this->company, now()->addHours(1)));

        Bus::fake();

        $this->artisan('travel:expire-pending-bookings')->assertExitCode(0);

        Bus::assertDispatched(ExpirePendingBookingsJob::class, fn (ExpirePendingBookingsJob $job): bool => $job->companyId === $this->company->id);
        Bus::assertDispatched(ExpirePendingBookingsJob::class, fn (ExpirePendingBookingsJob $job): bool => $job->companyId === $otherCompany->id);
        Bus::assertDispatchedCount(ExpirePendingBookingsJob::class, 2);
    }
}
