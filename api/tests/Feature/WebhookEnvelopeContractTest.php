<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Billing\Infrastructure\Services\WebhookDispatcher;
use App\Modules\Billing\Infrastructure\Services\WebhookEnvelopeBuilder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5744 (CRM PRE) — versionner les contrats API et événements CRM.
 *
 * L'enveloppe canonique des webhooks sortants est ADDITIVE et versionnée :
 * event, event_version, company_id, correlation_id, occurred_at, timestamp,
 * data + en-têtes Webhook-Id/Timestamp/Signature, X-Leopardo-Event,
 * X-Leopardo-Signature et X-Leopardo-Event-Version.
 */
class WebhookEnvelopeContractTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_test_endpoint_posts_versioned_envelope(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'test-secret',
            'active' => true,
        ]);

        Http::fake(['example.com/*' => Http::response('{"ok":true}', 200)]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/webhooks/{$endpoint->id}/test")->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'example.com/hook')) {
                return false;
            }

            $payload = $request->data();

            // Champs canoniques versionnés (issue #5744).
            if (($payload['event'] ?? null) !== 'test') {
                return false;
            }
            if (($payload['event_version'] ?? null) !== WebhookEnvelopeBuilder::CURRENT_VERSION) {
                return false;
            }
            if (! is_string($payload['company_id'] ?? null) || $payload['company_id'] === '') {
                return false;
            }
            if (! is_string($payload['correlation_id'] ?? null) || $payload['correlation_id'] === '') {
                return false;
            }
            if (! is_string($payload['occurred_at'] ?? null) || $payload['occurred_at'] === '') {
                return false;
            }

            // Rétro-compatibilité : champs hérités toujours présents.
            if (! isset($payload['timestamp']) || ! is_array($payload['data'])) {
                return false;
            }
            if (($payload['data']['test'] ?? null) !== true) {
                return false;
            }

            // En-têtes canoniques + version.
            if (! $request->hasHeader('Webhook-Id') || ! $request->hasHeader('Webhook-Timestamp')) {
                return false;
            }
            if (! $request->hasHeader('Webhook-Signature')) {
                return false;
            }
            if (! $request->hasHeader('X-Leopardo-Event', 'test')) {
                return false;
            }
            if (! $request->hasHeader('X-Leopardo-Signature')) {
                return false;
            }
            if (! $request->hasHeader('X-Leopardo-Event-Version', (string) WebhookEnvelopeBuilder::CURRENT_VERSION)) {
                return false;
            }

            // Signature Svix-compatible : v1=<hmac>,t=<ts>.
            $signatureHeader = $request->header('Webhook-Signature')[0] ?? '';
            if (! preg_match('/^v1=[0-9a-f]{64},t=\d+$/', $signatureHeader)) {
                return false;
            }

            return true;
        });
    }

    public function test_dispatch_uses_same_correlation_id_for_all_endpoints_of_a_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $companyId = $company->id;

        foreach (['https://example.com/a', 'https://example.com/b'] as $url) {
            WebhookEndpoint::create([
                'company_id' => $companyId,
                'url' => $url,
                'events' => ['employee.created'],
                'secret' => 'test-secret',
                'active' => true,
            ]);
        }

        Http::fake([
            'example.com/*' => Http::response('{"ok":true}', 200),
        ]);

        app(WebhookDispatcher::class)->dispatch($companyId, 'employee.created', ['id' => 1]);

        // QUEUE_CONNECTION=sync → DispatchWebhook s'exécute immédiatement.
        Http::assertSentCount(2);

        $correlationIds = [];
        $webhookIds = [];

        Http::assertSent(function ($request) use (&$correlationIds, &$webhookIds): bool {
            $correlationIds[] = $request->data()['correlation_id'] ?? null;
            $webhookIds[] = $request->header('Webhook-Id')[0] ?? null;

            return true;
        });

        $this->assertCount(1, array_unique($correlationIds), 'Un même dispatch partage le même correlation_id entre endpoints.');
        $this->assertNotSame('', $correlationIds[0]);
        $this->assertCount(2, array_unique($webhookIds), 'Chaque livraison porte un Webhook-Id distinct.');
    }

    public function test_dispatch_envelope_carries_tenant_and_occurred_at(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $companyId = $company->id;

        WebhookEndpoint::create([
            'company_id' => $companyId,
            'url' => 'https://example.com/hook',
            'events' => ['payroll.validated'],
            'secret' => 'test-secret',
            'active' => true,
        ]);

        Http::fake(['example.com/*' => Http::response('{"ok":true}', 200)]);

        app(WebhookDispatcher::class)->dispatch($companyId, 'payroll.validated', ['run_id' => 42]);

        Http::assertSent(function ($request) use ($companyId): bool {
            $payload = $request->data();

            if (($payload['event'] ?? null) !== 'payroll.validated') {
                return false;
            }
            if (($payload['event_version'] ?? null) !== 1) {
                return false;
            }
            if (($payload['company_id'] ?? null) !== $companyId) {
                return false;
            }
            if (! is_string($payload['occurred_at'] ?? null) || strtotime($payload['occurred_at']) === false) {
                return false;
            }
            if (($payload['data']['run_id'] ?? null) !== 42) {
                return false;
            }

            return true;
        });
    }

    public function test_dispatch_job_falls_back_when_no_correlation_provided(): void
    {
        // Rétro-compatibilité du job : sans correlation_id/occurred_at
        // (jobs déjà en file avant #5744), l'enveloppe reste complète.
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'test-secret',
            'active' => true,
        ]);

        Http::fake(['example.com/*' => Http::response('{"ok":true}', 200)]);

        DispatchWebhook::dispatchSync($endpoint, 'employee.created', ['id' => 1]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return ($payload['event'] ?? null) === 'employee.created'
                && ($payload['event_version'] ?? null) === 1
                && is_string($payload['correlation_id'] ?? null)
                && $payload['correlation_id'] !== ''
                && is_string($payload['occurred_at'] ?? null)
                && $payload['occurred_at'] !== '';
        });
    }
}
