<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookDelivery;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-API-006 — Outbound partner webhooks: dead-letter handling.
 *
 * Covers `DispatchWebhook::failed()` (called by the queue worker once all
 * retries are exhausted) and the manager-facing dead-letter list/replay
 * endpoints. See docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md.
 */
class WebhookDeadLetterTest extends TestCase
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

    public function test_job_failed_hook_creates_a_dead_lettered_delivery(): void
    {
        $company = Company::factory()->create();
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => true,
        ]);

        $job = new DispatchWebhook($endpoint, 'employee.created', ['id' => 42]);
        $job->failed(new RuntimeException('Connection refused'));

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'response_code' => 0,
        ]);

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->first();
        $this->assertNotNull($delivery);
        $this->assertNotNull($delivery->dead_lettered_at);
        $this->assertStringContainsString('Connection refused', (string) $delivery->response_body);
    }

    public function test_manager_can_list_dead_lettered_deliveries_for_own_endpoint(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => true,
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['event' => 'employee.created', 'data' => ['id' => 1]],
            'response_code' => 200,
            'response_body' => 'ok',
            'duration_ms' => 50,
        ]);
        $deadLetter = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['event' => 'employee.created', 'data' => ['id' => 2]],
            'response_code' => 0,
            'response_body' => 'timeout',
            'duration_ms' => 0,
            'dead_lettered_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/webhooks/{$endpoint->id}/dead-letters");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $deadLetter->id);
    }

    public function test_manager_cannot_list_dead_letters_for_another_companys_endpoint(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $ownCompany->id]);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $otherCompany->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/webhooks/{$endpoint->id}/dead-letters");

        $response->assertNotFound();
    }

    public function test_manager_can_replay_a_dead_lettered_delivery(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => false,
            'failure_count' => 10,
        ]);
        $deadLetter = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['event' => 'employee.created', 'data' => ['id' => 7]],
            'response_code' => 0,
            'response_body' => 'timeout',
            'duration_ms' => 0,
            'dead_lettered_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/dead-letters/{$deadLetter->id}/replay");

        $response->assertStatus(202);

        Queue::assertPushed(DispatchWebhook::class, function (DispatchWebhook $job) {
            return true;
        });

        $endpoint->refresh();
        $this->assertTrue($endpoint->active);
        $this->assertSame(0, $endpoint->failure_count);
    }

    public function test_replay_rejects_a_delivery_that_is_not_dead_lettered(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => true,
        ]);
        $successfulDelivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['event' => 'employee.created', 'data' => ['id' => 1]],
            'response_code' => 200,
            'response_body' => 'ok',
            'duration_ms' => 50,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/dead-letters/{$successfulDelivery->id}/replay");

        $response->assertNotFound();
        Queue::assertNotPushed(DispatchWebhook::class);
    }

    public function test_non_manager_cannot_list_or_replay_dead_letters(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => true,
        ]);
        $deadLetter = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['event' => 'employee.created', 'data' => ['id' => 1]],
            'response_code' => 0,
            'response_body' => 'timeout',
            'duration_ms' => 0,
            'dead_lettered_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/webhooks/{$endpoint->id}/dead-letters")->assertForbidden();
        $this->postJson("/api/v1/webhooks/{$endpoint->id}/dead-letters/{$deadLetter->id}/replay")->assertForbidden();
    }
}
