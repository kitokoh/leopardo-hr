<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Jobs\DeliverTravelWebhookJob;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookConsumer;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookSigner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-806 (#6097) — Webhooks sortants transporteurs.
 *
 * CRUD des abonnements (secret jamais exposé), consommation de l'outbox
 * → livraison HMAC signée, idempotence (rejeu sans doublon), retry/
 * backoff, dead-letter, RBAC.
 */
class TravelWebhookTest extends TestCase
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

    public function test_create_subscription_never_exposes_secret(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $carrier = app(TenantManager::class)->withinTenant($company, fn (): TravelCarrier => TravelCarrier::factory()->create());

        $response = $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'carrier_id' => $carrier->id,
            'url' => 'https://carrier.example.com/webhooks',
            'secret' => 'supersecretkey123456',
            'events' => ['travel.booking.confirmed.v1', 'travel.ticket.issued.v1'],
        ])->assertCreated();

        $body = $response->json('data');
        $this->assertArrayHasKey('secret_prefix', $body);
        $this->assertSame(8, strlen((string) $body['secret_prefix']));
        $this->assertStringNotContainsString('supersecretkey123456', $response->getContent());
        $this->assertStringNotContainsString('secret_encrypted', $response->getContent());
    }

    public function test_second_subscription_for_same_carrier_is_upsert(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $carrier = app(TenantManager::class)->withinTenant($company, fn (): TravelCarrier => TravelCarrier::factory()->create());

        $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'carrier_id' => $carrier->id,
            'url' => 'https://a.example.com/h',
            'secret' => 'firstsecretkey123456',
            'events' => ['travel.booking.confirmed.v1'],
        ])->assertCreated();

        $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'carrier_id' => $carrier->id,
            'url' => 'https://b.example.com/h',
            'secret' => 'secondsecretkey12345',
            'events' => ['travel.booking.cancelled.v1'],
        ])->assertCreated();

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelWebhookSubscription::query()->count();
        }));
    }

    public function test_webhook_subscriptions_require_manager(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'role' => 'employee']);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/travel/webhook-subscriptions')->assertForbidden();

        $carrier = app(TenantManager::class)->withinTenant($company, fn (): TravelCarrier => TravelCarrier::factory()->create());

        $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'carrier_id' => $carrier->id,
            'url' => 'https://carrier.example.com/h',
            'secret' => 'employeenosecret1234',
            'events' => ['travel.booking.confirmed.v1'],
        ])->assertForbidden();
    }

    public function test_outbox_event_creates_signed_delivery(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        Http::fake(['*' => Http::response(null, 200)]);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $carrier = TravelCarrier::factory()->create();
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 10, 'carrier_id' => $carrier->id]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED,
                'payment_status' => PaymentStatus::CONFIRMED,
            ]);

            TravelWebhookSubscription::query()->create([
                'company_id' => $booking->company_id,
                'carrier_id' => $carrier->id,
                'url' => 'https://carrier.example.com/webhooks',
                'secret_encrypted' => Crypt::encryptString('webhooksecret123456'),
                'events' => ['travel.booking.confirmed.v1'],
                'active' => true,
            ]);

            TravelOutboxEvent::query()->create([
                'company_id' => $booking->company_id,
                'event_type' => 'travel.booking.confirmed.v1',
                'payload_redacted' => ['booking_reference' => $booking->reference, 'trip_id' => $trip->id],
                'status' => TravelOutboxEvent::STATUS_PENDING,
                'idempotency_key' => 'webhook-test-'.uniqid(),
                'available_at' => now(),
            ]);
        });

        // Consommation directe (équivalent travel:outbox-dispatch).
        $events = app(TenantManager::class)->withinTenant($company, fn (): Collection => TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_PENDING)->get());

        foreach ($events as $event) {
            app(TenantManager::class)->withinTenant($company, function () use ($event): void {
                app(TravelWebhookConsumer::class)->handle(array_merge([
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'company_id' => $event->company_id,
                ], $event->payload_redacted ?? []));
            });
        }

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelWebhookDelivery::query()->count();
        }));

        Http::assertSent(function ($request): bool {
            return str_starts_with((string) $request->url(), 'https://carrier.example.com')
                && $request->hasHeader('X-Travel-Signature')
                && $request->hasHeader('X-Travel-Timestamp');
        });
    }

    public function test_signature_is_hmac_verifiable(): void
    {
        $signer = new TravelWebhookSigner;
        $payload = ['booking_reference' => 'BK-123', 'event_type' => 'travel.booking.confirmed.v1'];

        $signature = $signer->sign($payload, 'secret123');

        $this->assertTrue($signer->verify($signature, $signer->canonicalPayload($payload), 'secret123'));
        $this->assertFalse($signer->verify($signature, $signer->canonicalPayload($payload), 'wrong-secret'));
    }

    public function test_replay_does_not_create_duplicate_delivery(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        Http::fake(['*' => Http::response(null, 200)]);

        $carrier = app(TenantManager::class)->withinTenant($company, fn (): TravelCarrier => TravelCarrier::factory()->create());
        $trip = app(TenantManager::class)->withinTenant($company, function () use ($carrier): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 10, 'carrier_id' => $carrier->id]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            return $trip;
        });

        $event = app(TenantManager::class)->withinTenant($company, function () use ($trip): TravelOutboxEvent {
            $subscription = TravelWebhookSubscription::query()->create([
                'company_id' => $trip->company_id,
                'carrier_id' => $trip->carrier_id,
                'url' => 'https://carrier.example.com/webhooks',
                'secret_encrypted' => Crypt::encryptString('webhooksecret123456'),
                'events' => ['travel.booking.confirmed.v1'],
                'active' => true,
            ]);

            return TravelOutboxEvent::query()->create([
                'company_id' => $trip->company_id,
                'event_type' => 'travel.booking.confirmed.v1',
                'payload_redacted' => ['trip_id' => $trip->id],
                'status' => TravelOutboxEvent::STATUS_PENDING,
                'idempotency_key' => 'replay-test-'.uniqid(),
                'available_at' => now(),
            ]);
        });

        $consumer = app(TravelWebhookConsumer::class);
        $envelope = fn (): array => array_merge([
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'company_id' => $event->company_id,
        ], $event->payload_redacted ?? []);

        app(TenantManager::class)->withinTenant($company, fn () => $consumer->handle($envelope()));
        app(TenantManager::class)->withinTenant($company, fn () => $consumer->handle($envelope()));

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelWebhookDelivery::query()->count();
        }));
    }

    public function test_failed_delivery_retries_then_dead_letters(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        Http::fake(['*' => Http::response('boom', 500)]);

        $carrier = app(TenantManager::class)->withinTenant($company, fn (): TravelCarrier => TravelCarrier::factory()->create());
        $trip = app(TenantManager::class)->withinTenant($company, function () use ($carrier): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 10, 'carrier_id' => $carrier->id]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            return $trip;
        });

        $delivery = app(TenantManager::class)->withinTenant($company, function () use ($trip): TravelWebhookDelivery {
            $subscription = TravelWebhookSubscription::query()->create([
                'company_id' => $trip->company_id,
                'carrier_id' => $trip->carrier_id,
                'url' => 'https://carrier.example.com/webhooks',
                'secret_encrypted' => Crypt::encryptString('webhooksecret123456'),
                'events' => ['travel.booking.confirmed.v1'],
                'active' => true,
            ]);

            return TravelWebhookDelivery::query()->create([
                'company_id' => $trip->company_id,
                'subscription_id' => $subscription->id,
                'event_id' => 42,
                'event_type' => 'travel.booking.confirmed.v1',
                'payload_redacted' => ['trip_id' => $trip->id],
                'status' => TravelWebhookDelivery::STATUS_PENDING,
                'attempts' => 4, // Un échec de plus → dead-letter (MAX=5)
                'next_attempt_at' => now(),
            ]);
        });

        $job = new DeliverTravelWebhookJob($delivery->id, $delivery->subscription_id);
        $job->handle(app(TravelWebhookSigner::class));

        $fresh = app(TenantManager::class)->withinTenant($company, fn () => $delivery->refresh());
        $this->assertSame(TravelWebhookDelivery::STATUS_FAILED, $fresh->status);
        $this->assertSame(5, $fresh->attempts);
    }
}
