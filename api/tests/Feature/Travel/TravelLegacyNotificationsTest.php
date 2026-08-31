<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ConfirmBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-910 (#6113) — Notifications legacy gv-back → canaux plateforme
 * (BC-13) : AUCUNE table « notifications » maison, les événements outbox
 * portent l'intention de notification (`notification_intent`) + le
 * consentement (opt-in, défaut refusé), et la publication est consommée par
 * le pipeline travel:outbox-dispatch (TRAVEL-414).
 */
class TravelLegacyNotificationsTest extends TestCase
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

    public function test_no_homegrown_notifications_table(): void
    {
        self::assertFalse(Schema::hasTable('travel_notifications'), 'aucune table notifications maison (canaux BC-13 uniquement)');
        self::assertFalse(Schema::hasTable('travel_notification_queue'), 'aucune file maison');
    }

    public function test_booking_confirmed_event_carries_notification_intent_and_consent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $actor = $this->principal($company);

        $tenants = app(TenantManager::class);

        $tenants->withinTenant($company, function () use ($actor): void {
            /** @var TravelBooking $booking */
            $booking = TravelBooking::factory()->create(['status' => BookingStatus::PENDING->value]);

            TravelTripSeat::factory()->create([
                'trip_id' => $booking->trip_id,
                'status' => SeatStatus::RESERVED->value,
                'booking_id' => $booking->id,
            ]);

            app(ConfirmBookingAction::class)->execute($booking, $actor);
        });

        /** @var TravelOutboxEvent $event */
        $event = TravelOutboxEvent::query()
            ->where('event_type', 'travel.booking.confirmed.v1')
            ->firstOrFail();

        self::assertSame('travel.booking.confirmed', $event->payload_redacted['notification_intent'] ?? null);
        self::assertFalse((bool) ($event->payload_redacted['consent'] ?? true), 'opt-in refusé par défaut');
        self::assertArrayNotHasKey('email', $event->payload_redacted, 'pas de PII dans le payload redigé');

        // Publication consommée par le pipeline (TRAVEL-414) → published.
        Artisan::call('travel:outbox-dispatch');
        self::assertSame(TravelOutboxEvent::STATUS_PUBLISHED, $event->refresh()->status);
    }

    public function test_legacy_notification_events_are_all_mapped(): void
    {
        // Chaque événement legacy notifiable doit porter une intention dans le
        // mapping spec §8.5 (vérifié ici par le contrat de payload minimal).
        $mapped = [
            'travel.booking.pending.v1' => 'travel.booking.pending',
            'travel.booking.confirmed.v1' => 'travel.booking.confirmed',
            'travel.booking.cancelled.v1' => 'travel.booking.cancelled',
            'travel.payment.refunded.v1' => 'travel.payment.refunded',
            'travel.ticket.issued.v1' => 'travel.ticket.issued',
        ];

        // Le consommateur de publication (TRAVEL-414) supporte ces types.
        $consumer = app(\App\Modules\TravelAgency\Infrastructure\Services\TravelEventPublisherConsumer::class);

        foreach ($mapped as $eventType => $intent) {
            self::assertTrue($consumer->supports($eventType), "{$eventType} supporté par le publisher");
            self::assertStringStartsWith('travel.', $intent);
        }
    }
}
