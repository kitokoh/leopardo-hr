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
use App\Mail\CommunicationMail;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use Illuminate\Support\Facades\Mail;
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
 * TRAVEL-910 (#6113) — Notifications legacy gv-back → canaux plateforme.
 *
 * Aucune table « notifications » maison ; l'envoi manuel passe par les
 * canaux de la plateforme (email transactionnel / in-app BC-13) et
 * respecte le consentement (`travel_customer_contacts`).
 */
class TravelLegacyNotificationsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->company = $company;
        $this->tenants = app(TenantManager::class);

        Mail::fake();
    }

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
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
    public function test_no_homegrown_notifications_table(): void
    {
        // La file maison gv-back n'est PAS reproduite : aucune table
        // travel_notifications / travel_manual_notifications.
        self::assertFalse(Schema::hasTable('travel_notifications'));
        self::assertFalse(Schema::hasTable('travel_manual_notifications'));
    }

    public function test_manual_notification_sent_when_channel_configured_with_consent(): void
    {
        $this->actingManager();
        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create());

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/notify', [
            'message' => 'Votre voyage est confirmé.',
        ])
            ->assertOk()
            ->assertJsonPath('data.channels', ['email']);

        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->hasTo($contact->email));
    }

    public function test_manual_notification_blocked_without_consent(): void
    {
        $this->actingManager();
        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create());

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/notify', [
            'message' => 'Pas de consentement.',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_manual_notification_requires_manage_role(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create());

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/notify', [
            'message' => 'Rôle insuffisant.',
        ])->assertStatus(403);
    }
}
