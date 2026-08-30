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
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending.
 *
 * Un pending expiré libère EXACTEMENT ses sièges, passe en cancelled et
 * publie `travel.booking.cancelled.v1` (motif expired). Idempotent,
 * isolé par tenant, non-expiration avant délai.
 */
class TravelExpirePendingBookingsTest extends TestCase
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

    private function runCommand(): void
    {
        Artisan::call('travel:expire-pending-bookings', [
            '--company' => (string) $this->company->id,
        ]);
    }

    public function test_expired_pending_releases_exactly_its_seats(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->subMinutes(5),
            ]);

            TravelTripSeat::factory()->create([
                'trip_id' => $booking->trip_id,
                'booking_id' => $booking->id,
                'status' => SeatStatus::RESERVED->value,
            ]);
            TravelTripSeat::factory()->create([
                'trip_id' => $booking->trip_id,
                'booking_id' => $booking->id,
                'status' => SeatStatus::RESERVED->value,
            ]);
            // Siège d'un AUTRE booking (confirmé) — ne doit PAS être touché.
            $other = TravelBooking::factory()->create([
                'status' => BookingStatus::CONFIRMED->value,
                'expires_at' => null,
            ]);
            TravelTripSeat::factory()->create([
                'trip_id' => $other->trip_id,
                'booking_id' => $other->id,
                'status' => SeatStatus::SOLD->value,
            ]);
        });

        $this->runCommand();

        $this->tenants->withinTenant($this->company, function (): void {
            $booking = TravelBooking::query()
                ->where('status', BookingStatus::CANCELLED->value)
                ->firstOrFail();
            self::assertNull($booking->expires_at);

            self::assertSame(
                0,
                TravelTripSeat::query()->where('booking_id', $booking->id)->where('status', SeatStatus::RESERVED->value)->count(),
                'tous les sièges de la réservation expirée sont libérés',
            );
            self::assertSame(
                2,
                TravelTripSeat::query()->where('booking_id', $booking->id)->where('status', SeatStatus::FREE->value)->count(),
            );

            // Le siège SOLD de l'autre réservation est intact.
            self::assertSame(1, TravelTripSeat::query()->where('status', SeatStatus::SOLD->value)->count());
        });

        $event = TravelOutboxEvent::query()
            ->where('event_type', 'travel.booking.cancelled.v1')
            ->firstOrFail();
        self::assertSame('expired', $event->payload_redacted['reason']);
    }

    public function test_pending_not_yet_expired_stays_pending(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->addHours(2),
            ]);
        });

        $this->runCommand();

        $this->tenants->withinTenant($this->company, function (): void {
            self::assertSame(1, TravelBooking::query()->where('status', BookingStatus::PENDING->value)->count());
        });
        self::assertSame(0, TravelOutboxEvent::query()->count());
    }

    public function test_rerun_is_idempotent(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->subMinutes(5),
            ]);
        });

        $this->runCommand();
        $this->runCommand();

        self::assertSame(
            1,
            TravelOutboxEvent::query()->where('event_type', 'travel.booking.cancelled.v1')->count(),
            'une réservation déjà annulée n\'est jamais re-traitée',
        );
    }

    public function test_other_tenants_are_isolated(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($companyB, function (): void {
            TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING->value,
                'expires_at' => now()->subMinutes(5),
            ]);
        });

        $this->runCommand();

        self::assertSame(0, TravelOutboxEvent::query()->count(), 'le tenant ciblé ne touche pas aux bookings des autres');
    }
}
