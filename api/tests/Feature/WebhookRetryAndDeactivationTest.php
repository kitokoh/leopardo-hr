<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #6550 — fiabilité des webhooks sortants :
 *
 * - une réponse 5xx du partenaire est rethrown (retry queue avec backoff,
 *   puis dead-letter via DispatchWebhook::failed()) au lieu d'être perdue
 *   silencieusement ;
 * - la désactivation après N échecs consécutifs (>= 10) s'applique aussi aux
 *   exceptions (DNS/timeout), pas seulement aux réponses non-2xx ;
 * - un `POST /webhooks/{id}/test` réussi réactive un endpoint désactivé.
 */
class WebhookRetryAndDeactivationTest extends TestCase
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

    private function endpoint(Company $company, bool $active = true): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://hook.example.com/receive',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => $active,
        ]);
    }

    public function test_partner_5xx_is_rethrown_for_queue_retry_and_counts_failure(): void
    {
        $company = Company::factory()->create();
        $endpoint = $this->endpoint($company);

        Http::fake([
            'https://hook.example.com/*' => Http::response('service unavailable', 503),
        ]);

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 1]);

        try {
            $job->handle();
            $this->fail('Une réponse 5xx doit être rethrown pour déclencher le retry queue.');
        } catch (RuntimeException) {
            // attendu — la file retente avec backoff puis dead-letter.
        }

        $endpoint->refresh();
        $this->assertSame(1, (int) $endpoint->failure_count);
        $this->assertTrue((bool) $endpoint->active, 'Un seul 5xx ne désactive pas encore l’endpoint.');
        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'response_code' => 503,
        ]);
    }

    public function test_consecutive_failures_deactivate_the_endpoint(): void
    {
        $company = Company::factory()->create();
        $endpoint = $this->endpoint($company);
        $endpoint->update(['failure_count' => 9]);

        Http::fake([
            'https://hook.example.com/*' => Http::response('down', 503),
        ]);

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 1]);

        try {
            $job->handle();
        } catch (RuntimeException) {
            // attendu
        }

        $endpoint->refresh();
        $this->assertSame(10, (int) $endpoint->failure_count);
        $this->assertFalse((bool) $endpoint->active, '10 échecs consécutifs → endpoint désactivé.');
    }

    public function test_dns_exception_path_also_counts_toward_deactivation(): void
    {
        $company = Company::factory()->create();
        $endpoint = $this->endpoint($company);
        $endpoint->update(['failure_count' => 9]);

        Http::fake(fn () => throw new RuntimeException('Connection refused (DNS)'));

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 1]);

        try {
            $job->handle();
        } catch (RuntimeException) {
            // attendu — rethrown pour retry
        }

        $endpoint->refresh();
        $this->assertSame(10, (int) $endpoint->failure_count);
        $this->assertFalse((bool) $endpoint->active, 'L’échec DNS compte aussi pour la désactivation.');
    }

    public function test_successful_2xx_resets_failure_count(): void
    {
        $company = Company::factory()->create();
        $endpoint = $this->endpoint($company);
        $endpoint->update(['failure_count' => 4]);

        Http::fake([
            'https://hook.example.com/*' => Http::response('ok', 200),
        ]);

        (new DispatchWebhook($endpoint, 'employee.created', ['id' => 1]))->handle();

        $endpoint->refresh();
        $this->assertSame(0, (int) $endpoint->failure_count);
        $this->assertTrue((bool) $endpoint->active);
    }

    public function test_successful_test_reactivates_a_deactivated_endpoint(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $endpoint = $this->endpoint($company, active: false);

        Sanctum::actingAs($manager);
        Http::fake([
            'https://hook.example.com/*' => Http::response('ok', 200),
        ]);

        $this->postJson('/api/v1/webhooks/'.$endpoint->id.'/test')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $endpoint->refresh();
        $this->assertTrue((bool) $endpoint->active, 'Test réussi → endpoint réactivé.');
        $this->assertSame(0, (int) $endpoint->failure_count);
    }
}
