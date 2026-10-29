<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Queue\OutboundWebhookTransport;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Jobs\Services\HttpOutboundWebhookTransport;
use App\Modules\TravelAgency\Domain\Enums\TravelWebhookDeliveryStatus;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookDispatcher;
use App\Shared\Exceptions\OutboundWebhookBlockedException;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-021 (#8210) — le transport des webhooks sortants est unique, et il
 * ferme le trou anti-SSRF de la verticale Travel.
 *
 * Avant ce lot, `TravelWebhookDispatcher` livrait avec
 * `Http::withoutVerifying()` : aucun certificat TLS vérifié, aucune garde
 * d'hôte. L'URL venant d'un abonné, un tenant pouvait faire poster le payload
 * signé vers `https://169.254.169.254/…` (métadonnées cloud) ou vers un
 * service interne. Ces tests échouent sur le code d'avant.
 */
final class OutboundWebhookTransportTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_transport_is_bound_to_the_single_implementation(): void
    {
        self::assertInstanceOf(
            HttpOutboundWebhookTransport::class,
            app(OutboundWebhookTransport::class),
        );
    }

    public function test_transport_refuses_a_non_https_url(): void
    {
        $this->expectException(OutboundWebhookBlockedException::class);

        app(OutboundWebhookTransport::class)->send('http://carrier.example.test/hook', '{}', []);
    }

    public function test_transport_refuses_a_private_host(): void
    {
        $this->expectException(OutboundWebhookBlockedException::class);

        app(OutboundWebhookTransport::class)->send('https://127.0.0.1/hook', '{}', []);
    }

    public function test_transport_refuses_the_cloud_metadata_address(): void
    {
        $this->expectException(OutboundWebhookBlockedException::class);

        app(OutboundWebhookTransport::class)->send('https://169.254.169.254/latest/meta-data/', '{}', []);
    }

    public function test_transport_reports_status_body_and_duration_without_throwing_on_5xx(): void
    {
        Http::fake([
            'https://carrier.example.test/hook' => Http::response('boom', 503),
        ]);

        $response = app(OutboundWebhookTransport::class)->send(
            'https://carrier.example.test/hook',
            '{"a":1}',
            ['X-Test' => 'yes'],
        );

        self::assertSame(503, $response->status);
        self::assertSame('boom', $response->body);
        self::assertFalse($response->successful());
        self::assertGreaterThanOrEqual(0, $response->durationMs);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://carrier.example.test/hook'
            && $request->body() === '{"a":1}'
            && $request->hasHeader('X-Test', 'yes'));
    }

    /**
     * Le dispatcher Travel doit désormais refuser un abonnement pointant vers
     * un hôte interne, et le passer en dead-letter immédiatement : réessayer
     * cinq fois un hôte qui ne deviendra jamais public ne fait que retarder
     * la même conclusion.
     */
    public function test_travel_dispatcher_dead_letters_a_blocked_host_without_retrying(): void
    {
        Http::fake();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $subscription = app(TenantManager::class)->withinTenant($company, fn () => TravelWebhookSubscription::factory()->create([
            'url' => 'https://169.254.169.254/latest/meta-data/',
        ]));

        $delivery = app(TenantManager::class)->withinTenant($company, fn () => TravelWebhookDelivery::factory()->create([
            'subscription_id' => $subscription->id,
            'event_type' => 'travel.booking.confirmed.v1',
        ]));

        self::assertFalse(app(TravelWebhookDispatcher::class)->deliver($delivery));

        $delivery->refresh();
        self::assertSame(TravelWebhookDeliveryStatus::DEAD, $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertNull($delivery->next_attempt_at);

        // Aucune requête n'a été émise vers l'hôte interdit.
        Http::assertNothingSent();
    }
}
