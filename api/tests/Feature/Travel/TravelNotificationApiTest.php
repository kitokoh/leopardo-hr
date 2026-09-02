<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\CommunicationMail;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationConsent;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationLog;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-415 (#6067) — Notifications voyageur (canaux BC-13 + consentement).
 *
 * Couvre le critère d'acceptation (« aucune notification sans canal
 * configuré et consentement »), l'envoi mail réel quand canal + consentement
 * sont en place, le canal WhatsApp non configuré → skipped, et la
 * notification massive à l'annulation d'un trajet.
 */
class TravelNotificationApiTest extends TestCase
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
     * @return array{booking: TravelBooking, company: Company}
     */
    private function bookingWithContact(Company $company, bool $consent, bool $withConsentRow = true): array
    {
        $booking = app(TenantManager::class)->withinTenant($company, function () use ($company, $consent, $withConsentRow): TravelBooking {
            $trip = TravelTrip::factory()->create(['status' => 'published']);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'contact_email' => 'client@example.com',
                'contact_phone' => '+237600000000',
                'notify_consent' => $consent,
                'consent_recorded_at' => $consent ? now() : null,
            ]);

            if ($consent && $withConsentRow) {
                TravelNotificationConsent::query()->create([
                    'company_id' => $company->id,
                    'contact_identifier' => 'client@example.com',
                    'channel' => TravelNotificationConsent::CHANNEL_MAIL,
                    'source' => 'booking',
                    'granted_at' => now(),
                ]);
                TravelNotificationConsent::query()->create([
                    'company_id' => $company->id,
                    'contact_identifier' => '+237600000000',
                    'channel' => TravelNotificationConsent::CHANNEL_WHATSAPP,
                    'source' => 'booking',
                    'granted_at' => now(),
                ]);
            }

            return $booking;
        });

        return ['booking' => $booking, 'company' => $company];
    }

    private function dispatch(string $eventType, array $payload): void
    {
        $consumers = app(TravelOutboxConsumerRegistry::class)->consumersFor($eventType);
        $this->assertNotEmpty($consumers);

        foreach ($consumers as $consumer) {
            $consumer->handle($payload);
        }
    }

    public function test_no_notification_without_consent(): void
    {
        Mail::fake();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->bookingWithContact($company, consent: false);

        $this->dispatch('travel.booking.confirmed.v1', [
            'company_id' => $company->id,
            'event_id' => 1,
            'event_type' => 'travel.booking.confirmed.v1',
            'booking_reference' => $booking->reference,
        ]);

        Mail::assertNothingSent();
        $this->assertSame(1, TravelNotificationLog::query()
            ->where('company_id', $company->id)
            ->where('status', TravelNotificationLog::STATUS_SKIPPED)
            ->count());
    }

    public function test_mail_sent_when_channel_configured_and_consent_granted(): void
    {
        Mail::fake();
        config()->set('travel.notifications.enabled_channels', ['mail']);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->bookingWithContact($company, consent: true);

        $this->dispatch('travel.booking.confirmed.v1', [
            'company_id' => $company->id,
            'event_id' => 42,
            'event_type' => 'travel.booking.confirmed.v1',
            'booking_reference' => $booking->reference,
        ]);

        Mail::assertSent(CommunicationMail::class, fn ($mail) => $mail->hasTo('client@example.com'));
        $this->assertSame(1, TravelNotificationLog::query()
            ->where('company_id', $company->id)
            ->where('status', TravelNotificationLog::STATUS_SENT)
            ->count());
    }

    public function test_whatsapp_channel_without_configuration_is_skipped(): void
    {
        Mail::fake();
        config()->set('travel.notifications.enabled_channels', ['mail', 'whatsapp']);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->bookingWithContact($company, consent: true);

        $this->dispatch('travel.booking.confirmed.v1', [
            'company_id' => $company->id,
            'event_id' => 7,
            'event_type' => 'travel.booking.confirmed.v1',
            'booking_reference' => $booking->reference,
        ]);

        // Mail envoyé, WhatsApp tracé skipped (canal non configuré).
        Mail::assertSent(CommunicationMail::class);
        $this->assertSame(1, TravelNotificationLog::query()
            ->where('company_id', $company->id)
            ->where('channel', 'whatsapp')
            ->where('status', TravelNotificationLog::STATUS_SKIPPED)
            ->count());
    }

    public function test_trip_cancellation_notifies_all_consenting_contacts(): void
    {
        Mail::fake();
        config()->set('travel.notifications.enabled_channels', ['mail']);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = app(TenantManager::class)->withinTenant($company, function () use ($company): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published']);

            foreach (['a@example.com', 'b@example.com'] as $i => $email) {
                $booking = TravelBooking::factory()->create([
                    'trip_id' => $trip->id,
                    'contact_email' => $email,
                    'notify_consent' => true,
                ]);
                TravelNotificationConsent::query()->create([
                    'company_id' => $company->id,
                    'contact_identifier' => $email,
                    'channel' => TravelNotificationConsent::CHANNEL_MAIL,
                    'source' => 'booking',
                    'granted_at' => now(),
                ]);
            }

            return $trip;
        });

        $this->dispatch('travel.trip.cancelled.v1', [
            'company_id' => $company->id,
            'event_id' => 99,
            'event_type' => 'travel.trip.cancelled.v1',
            'trip_id' => $trip->id,
            'reason' => 'Incident matériel',
        ]);

        Mail::assertSent(CommunicationMail::class, fn ($mail) => $mail->hasTo('a@example.com'));
        Mail::assertSent(CommunicationMail::class, fn ($mail) => $mail->hasTo('b@example.com'));
    }

    public function test_notification_uses_locale_template(): void
    {
        Mail::fake();
        config()->set('travel.notifications.enabled_channels', ['mail']);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->bookingWithContact($company, consent: true);

        // Locale EN demandée dans le payload → titre anglais.
        $this->dispatch('travel.booking.confirmed.v1', [
            'company_id' => $company->id,
            'event_id' => 4242,
            'event_type' => 'travel.booking.confirmed.v1',
            'booking_reference' => $booking->reference,
            'locale' => 'en',
        ]);

        Mail::assertSent(CommunicationMail::class, fn ($mail) => $mail->hasTo('client@example.com'));
        Mail::assertSent(CommunicationMail::class, fn ($mail) => str_contains((string) $mail->subjectLine, 'Booking confirmed'));
    }
}
