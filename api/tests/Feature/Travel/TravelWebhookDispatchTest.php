<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TravelWebhookDeliveryStatus;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookDispatcher;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookSecretService;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-806 (#6097) — Dispatch des webhooks : matérialisation idempotente,
 * signature HMAC, retry/backoff, dead-letter, redaction du payload.
 */
class TravelWebhookDispatchTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_delivery_is_signed_with_hmac_and_payload_redacted(): void
    {
        Http::fake([
            'https://carrier.example.test/hooks/travel' => Http::response('ok', 200),
        ]);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $subscription = app(TenantManager::class)->withinTenant($company, fn () => TravelWebhookSubscription::factory()->create([
            'url' => 'https://carrier.example.test/hooks/travel',
            'events' => ['travel.booking.confirmed.v1'],
        ]));

        $delivery = app(TenantManager::class)->withinTenant($company, fn () => TravelWebhookDelivery::factory()->create([
            'subscription_id' => $subscription->id,
            'event_type' => 'travel.booking.confirmed.v1',
            'payload_redacted' => ['booking_reference' => 'GV-2026-0001', 'token' => 'should-never-leak'],
        ]));

        $delivered = app(TravelWebhookDispatcher::class)->deliver($delivery);

        self::assertTrue($delivered);

        Http::assertSent(function ($request) use ($subscription): bool {
            $body = $request->body();
            $expectedSignature = (new TravelWebhookDispatcher)->signature($body, app(TravelWebhookSecretService::class)->get($subscription));

            return $request->url() === 'https://carrier.example.test/hooks/travel'
                && $request->hasHeader('X-Leopardo-Signature', $expectedSignature)
                && $request->hasHeader('X-Leopardo-Event', 'travel.booking.confirmed.v1')
                && ! str_contains($body, 'should-never-leak')
                && str_contains($body, 'GV-2026-0001');
        });

        $delivery->refresh();
        self::assertSame(TravelWebhookDeliveryStatus::SENT, $delivery->status);
        self::assertNotNull($delivery->delivered_at);
    }

    public function test_failed_delivery_retries_with_backoff_then_dead_letter(): void
    {
        Http::fake([
            'https://carrier.example.test/hooks/retry' => Http::response('nope', 500),
        ]);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $subscription = app(TenantManager::class)->withinTenant($company, fn () => TravelWebhookSubscription::factory()->create([
            'url' => 'https://carrier.example.test/hooks/retry',
        ]));

        $dispatcher = app(TravelWebhookDispatcher::class);
        $delivery = app(TenantManager::class)->withinTenant($company, fn () => TravelWebhookDelivery::factory()->create([
            'subscription_id' => $subscription->id,
            'event_type' => 'travel.booking.confirmed.v1',
        ]));

        // 1er essai → failed + backoff
        self::assertFalse($dispatcher->deliver($delivery));
        $delivery->refresh();
        self::assertSame(TravelWebhookDeliveryStatus::FAILED, $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertNotNull($delivery->next_attempt_at);

        // Épuise les tentatives restantes jusqu'au dead-letter
        for ($i = 2; $i <= TravelWebhookDispatcher::MAX_ATTEMPTS; $i++) {
            $dispatcher->deliver($delivery);
            $delivery->refresh();
        }

        self::assertSame(TravelWebhookDeliveryStatus::DEAD, $delivery->status);
        self::assertSame(TravelWebhookDispatcher::MAX_ATTEMPTS, $delivery->attempts);
    }

    public function test_command_materializes_deliveries_idempotently_from_outbox(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $subscription = TravelWebhookSubscription::factory()->create([
                'events' => ['travel.booking.confirmed.v1'],
            ]);
            TravelOutboxEvent::factory()->create([
                'event_type' => 'travel.booking.confirmed.v1',
                'payload_redacted' => ['booking_reference' => 'GV-2026-0001'],
            ]);

            // Première passe : matérialisation.
            $this->artisan('travel:webhook-dispatch')
                ->expectsOutputToContain('matérialisées')
                ->assertExitCode(0);

            self::assertSame(
                1,
                TravelWebhookDelivery::query()->where('subscription_id', $subscription->id)->count(),
            );

            // Seconde passe : idempotent — aucun doublon.
            $this->artisan('travel:webhook-dispatch')->assertExitCode(0);
            self::assertSame(
                1,
                TravelWebhookDelivery::query()->where('subscription_id', $subscription->id)->count(),
            );
        });
    }

    public function test_inactive_subscription_receives_nothing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $subscription = TravelWebhookSubscription::factory()->create([
                'events' => ['travel.booking.confirmed.v1'],
                'active' => false,
            ]);
            TravelOutboxEvent::factory()->create(['event_type' => 'travel.booking.confirmed.v1']);

            $this->artisan('travel:webhook-dispatch')->assertExitCode(0);

            self::assertSame(0, TravelWebhookDelivery::query()->where('subscription_id', $subscription->id)->count());
        });
    }
}
