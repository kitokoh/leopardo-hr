<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Anti-SSRF guard on manager-configurable outbound webhooks.
 *
 * See docs/security/AUDIT_API_2026-07-19.md, section 2.
 */
class WebhookSsrfGuardTest extends TestCase
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

    /** @return array<string, array{0: string}> */
    public static function disallowedUrls(): array
    {
        return [
            'loopback IPv4' => ['https://127.0.0.1/hook'],
            'loopback hostname' => ['https://localhost/hook'],
            'private RFC1918 10.x' => ['https://10.0.0.5/hook'],
            'private RFC1918 192.168.x' => ['https://192.168.1.10/hook'],
            'link-local incl. cloud metadata' => ['https://169.254.169.254/latest/meta-data'],
            'plain http (not https)' => ['http://example.com/hook'],
        ];
    }

    /** @dataProvider disallowedUrls */
    public function test_store_rejects_private_or_insecure_webhook_url(string $url): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/webhooks', [
            'url' => $url,
            'events' => ['employee.created'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('webhook_endpoints', 0);
    }

    public function test_store_accepts_public_https_webhook_url(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('webhook_endpoints', [
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
        ]);
    }

    public function test_update_rejects_private_webhook_url(): void
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
        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/webhooks/{$endpoint->id}", [
            'url' => 'https://192.168.0.1/internal',
        ]);

        $response->assertStatus(422);
        $this->assertSame('https://example.com/hook', $endpoint->fresh()->url);
    }
}
