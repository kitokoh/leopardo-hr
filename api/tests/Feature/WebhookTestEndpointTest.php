<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\WebhookDelivery;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA wave 2026-08-14 — T002 (#2227).
 *
 * POST /webhooks/{webhookEndpoint}/test — le bouton « Tester » de l'admin SPA
 * (WebhooksView.vue:189) appelait une route inexistante (404). L'action doit
 * poster un payload de test synchrone, tracer la livraison dans
 * `webhook_deliveries` et retourner statut/HTTP/durée.
 */
class WebhookTestEndpointTest extends TestCase
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

    private function makeEndpoint(string $url = 'https://example.com/hook', ?string $companyId = null): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'company_id' => $companyId ?? Company::factory()->create()->id,
            'url' => $url,
            'events' => ['employee.created'],
            'secret' => 'test-secret',
            'active' => true,
        ]);
    }

    public function test_webhook_test_posts_payload_and_records_delivery(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $endpoint = $this->makeEndpoint(companyId: $company->id);

        Http::fake([
            'example.com/*' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/test");

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('http_status', 200);
        $response->assertJsonStructure(['message', 'status', 'http_status', 'duration_ms', 'delivery']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'example.com/hook')
                && $request->hasHeader('X-Leopardo-Event', 'test')
                && $request->hasHeader('Webhook-Signature');
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'test',
            'response_code' => 200,
        ]);

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame('test', $delivery->event);
        $this->assertSame(200, (int) $delivery->response_code);
    }

    public function test_webhook_test_reports_error_status_when_target_fails(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $endpoint = $this->makeEndpoint(companyId: $company->id);

        Http::fake([
            'example.com/*' => Http::response('boom', 500),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/test");

        $response->assertOk();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('http_status', 500);

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'test',
            'response_code' => 500,
        ]);
    }

    public function test_webhook_test_422_when_url_not_public_https(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $endpoint = $this->makeEndpoint(url: 'http://10.0.0.5/hook', companyId: $company->id);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/test");

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'blocked');
    }

    public function test_webhook_test_404_for_cross_tenant_endpoint(): void
    {
        $ownCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $ownCompany->id]);
        $otherEndpoint = $this->makeEndpoint();

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$otherEndpoint->id}/test");

        $response->assertNotFound();
    }

    public function test_webhook_test_forbidden_for_non_principal_manager(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $endpoint = $this->makeEndpoint(companyId: $company->id);

        Sanctum::actingAs($employee);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/test");

        $response->assertForbidden();
    }
}
