<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #6550 (audit fiabilité H5) — DispatchWebhook :
 *  1. un non-2xx partenaire doit être RETHROWN → le job repasse par les
 *     retries (tries/backoff) puis par failed() → dead-letter. Avant : le
 *     job « réussissait », aucun retry, aucune dead-letter → perte sèche.
 *  2. un succès RÉACTIVE l'endpoint (active=true, failure_count=0) — la
 *     désactivation après N échecs n'est plus définitive.
 */
class WebhookDispatchRetryTest extends TestCase
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

    private function endpoint(bool $active = true, int $failureCount = 0): WebhookEndpoint
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => $active,
            'failure_count' => $failureCount,
        ]);

        return $endpoint;
    }

    public function test_non_2xx_response_is_rethrown_so_the_job_is_retried(): void
    {
        $endpoint = $this->endpoint();
        Http::fake([
            'example.com/*' => Http::response('upstream error', 503),
        ]);

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 42]);

        try {
            $job->handle();
            $this->fail('Un 503 partenaire doit lever une exception pour déclencher les retries du job.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'response_code' => 503,
        ]);

        // Le compteur d'échecs a été incrémenté (désactivation après N échecs
        // consécutifs — la désactivation ne doit PAS être immédiate).
        $this->assertSame(1, $endpoint->refresh()->failure_count);
        $this->assertTrue((bool) $endpoint->refresh()->active);
    }

    public function test_success_after_failures_reactivates_the_endpoint(): void
    {
        // Endpoint désactivé après une série d'échecs (failure_count >= 10).
        $endpoint = $this->endpoint(active: false, failureCount: 10);
        Http::fake([
            'example.com/*' => Http::response('ok', 200),
        ]);

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 42]);
        $job->handle();

        $fresh = $endpoint->refresh();
        $this->assertTrue((bool) $fresh->active, 'Un succès doit réactiver l\'endpoint (issue #6550).');
        $this->assertSame(0, $fresh->failure_count);
        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'response_code' => 200,
        ]);
    }

    public function test_transient_dns_failure_is_rethrown_without_immediate_deactivation(): void
    {
        $endpoint = $this->endpoint(active: true, failureCount: 5);
        Http::fake([
            'example.com/*' => fn () => throw new RuntimeException('Connection refused'),
        ]);

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 42]);

        try {
            $job->handle();
            $this->fail('Un échec réseau doit lever une exception (retry du job).');
        } catch (RuntimeException) {
            // attendu — le worker retentera (tries=3, backoff).
        }

        $fresh = $endpoint->refresh();
        $this->assertSame(6, $fresh->failure_count);
        $this->assertTrue((bool) $fresh->active, 'Un aléa transitoire ne désactive pas l\'endpoint immédiatement.');
    }
}
