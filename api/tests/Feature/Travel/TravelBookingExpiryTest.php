<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ExpireBookingsAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-418 (#6070) — Expiration des réservations pending.
 *
 * Libération des sièges, événement outbox, idempotence (une réservation
 * déjà expirée n'est jamais re-touchée) et isolation par tenant.
 */
class TravelBookingExpiryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_expired_pending_booking_releases_seats(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 10]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::PENDING,
                'expires_at' => now()->subMinutes(5),
            ]);

            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('status', SeatStatus::FREE)
                ->first()
                ?->forceFill([
                    'status' => SeatStatus::RESERVED,
                    'booking_id' => $booking->id,
                    'reserved_until' => now()->subMinutes(5),
                ])->save();
        });

        $expired = app(ExpireBookingsAction::class)->execute();

        $this->assertSame(1, $expired);

        $this->assertSame(0, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTripSeat::query()->where('status', SeatStatus::RESERVED)->count();
        }));

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOutboxEvent::query()
                ->where('event_type', 'travel.booking.expired.v1')
                ->count();
        }));
    }

    public function test_not_yet_expired_booking_is_untouched(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        $this->assertSame(0, app(ExpireBookingsAction::class)->execute());
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelBooking::query()->where('status', BookingStatus::PENDING)->count();
        }));
    }

    public function test_expiry_is_idempotent_on_replay(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING,
                'expires_at' => now()->subMinutes(5),
            ]);
        });

        $this->assertSame(1, app(ExpireBookingsAction::class)->execute());
        // Rejeu : la réservation est déjà cancelled → 0.
        $this->assertSame(0, app(ExpireBookingsAction::class)->execute());
    }
}
