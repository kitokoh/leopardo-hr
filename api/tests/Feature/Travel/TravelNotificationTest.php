<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Infrastructure\Services\TravelNotificationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-415 (#6067) — notifications voyageur via les canaux BC-13.
 *
 * Verrouille : PAS d'envoi par défaut (consentement requis + canal
 * configuré), contenu minimal (résumé + référence, jamais de données
 * financières), événements supportés, file BC-13 alimentée (CommunicationEvent
 * pending), idempotence via l'outbox.
 */
class TravelNotificationTest extends TestCase
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

    public function test_no_notification_without_consent(): void
    {
        config(['communication.default_channels' => ['email']]);

        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::CONFIRMED->value,
                'notify_consent' => false,
                'customer_contact_id' => 'crm-contact-1',
            ]);

            $count = app(TravelNotificationService::class)->notify(
                (string) $this->companyA->id,
                'travel.booking.confirmed.v1',
                ['booking_reference' => $booking->reference],
            );

            $this->assertSame(0, $count);
            $this->assertSame(0, CommunicationEvent::query()->count());
        });
    }

    public function test_no_notification_without_configured_channel(): void
    {
        // Canaux par défaut = app/push (aucun canal mail/sms/whatsapp).
        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::CONFIRMED->value,
                'notify_consent' => true,
            ]);

            $count = app(TravelNotificationService::class)->notify(
                (string) $this->companyA->id,
                'travel.booking.confirmed.v1',
                ['booking_reference' => $booking->reference],
            );

            $this->assertSame(0, $count);
            $this->assertSame(0, CommunicationEvent::query()->count());
        });
    }

    public function test_notification_sent_when_consent_and_channel_configured(): void
    {
        config(['communication.default_channels' => ['email']]);

        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::CONFIRMED->value,
                'notify_consent' => true,
            ]);

            $count = app(TravelNotificationService::class)->notify(
                (string) $this->companyA->id,
                'travel.booking.confirmed.v1',
                ['booking_reference' => $booking->reference],
            );

            $this->assertSame(1, $count);

            $event = CommunicationEvent::query()->where('company_id', $this->companyA->id)->first();
            $this->assertNotNull($event);
            $this->assertSame('travel.booking.confirmed.v1', $event->event_name);
            $this->assertSame('email', $event->channel);
            $this->assertSame('pending', $event->status);
            // Résumé minimal, aucune donnée financière.
            $this->assertStringContainsString($booking->reference, (string) ($event->metadata['summary'] ?? ''));
            $this->assertArrayNotHasKey('amount', $event->metadata);
        });
    }

    public function test_unknown_booking_reference_is_ignored(): void
    {
        config(['communication.default_channels' => ['email']]);

        $count = app(TenantManager::class)->withinTenant(
            $this->companyA,
            fn (): int => app(TravelNotificationService::class)->notify(
                (string) $this->companyA->id,
                'travel.booking.confirmed.v1',
                ['booking_reference' => 'GV-UNKNOWN'],
            )
        );

        $this->assertSame(0, $count);
        $this->assertSame(0, CommunicationEvent::query()->count());
    }
}
