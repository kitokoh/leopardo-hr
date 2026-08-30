<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\CommunicationMail;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationConsent;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-910 (#6113) — Notifications legacy gv-back → canaux plateforme.
 *
 * Critère d'acceptation : AUCUNE table « notifications » maison ; les
 * canaux plateforme BC-13 sont utilisés (avec consentement). Ce test
 * vérifie (1) l'absence de table legacy et (2) que le chemin de dispatch
 * passe bien par le consommateur de notifications BC-13.
 */
class TravelLegacyNotificationContractTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_no_legacy_notification_table_exists(): void
    {
        // La file maison gv-back n'est pas reproduite : aucune migration ne
        // crée de table de notifications dédiée au legacy.
        $migrations = glob(database_path('migrations/tenant/*travel*notification*'));

        foreach ($migrations ?: [] as $migration) {
            $this->assertStringContainsString('travel_notification_', (string) $migration);
        }

        // Les seules tables travel_notification_* sont celles du socle
        // BC-13 (consentements + journal d'audit) — pas de file maison.
        foreach (['travel_notifications', 'travel_mail_queue', 'travel_legacy_notifications'] as $legacy) {
            $this->assertFalse(Schema::hasTable($legacy), $legacy.' ne doit pas exister');
        }
    }

    public function test_legacy_event_flows_through_platform_channels(): void
    {
        Mail::fake();
        config()->set('travel.notifications.enabled_channels', ['mail']);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($employee);

        $booking = app(TenantManager::class)->withinTenant($company, function () use ($company): TravelBooking {
            $trip = TravelTrip::factory()->create(['status' => 'published']);
            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'contact_email' => 'legacy-client@example.com',
                'notify_consent' => true,
            ]);

            TravelNotificationConsent::query()->create([
                'company_id' => $company->id,
                'contact_identifier' => 'legacy-client@example.com',
                'channel' => TravelNotificationConsent::CHANNEL_MAIL,
                'source' => 'booking',
                'granted_at' => now(),
            ]);

            return $booking;
        });

        // Événement legacy (même type d'événement que gv-back) → dispatch
        // via le consommateur de notifications BC-13.
        $consumer = app(TravelOutboxConsumerRegistry::class)->consumersFor('travel.booking.confirmed.v1');
        $this->assertNotEmpty($consumer);

        foreach ($consumer as $c) {
            $c->handle([
                'company_id' => $company->id,
                'event_id' => 999,
                'event_type' => 'travel.booking.confirmed.v1',
                'booking_reference' => $booking->reference,
            ]);
        }

        Mail::assertSent(CommunicationMail::class, fn ($mail) => $mail->hasTo('legacy-client@example.com'));
    }
}
