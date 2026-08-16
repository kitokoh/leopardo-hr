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
 * #3949 : la gestion des webhooks est réservée aux managers `principal`.
 * Garde unifiée via WebhookEndpointPolicy — les managers non-principal
 * (rh, département) reçoivent 403 sur store/update/test/destroy.
 */
class WebhookRbacTest extends TestCase
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

    /** @return array{company: Company, rh: Employee, endpoint: WebhookEndpoint} */
    private function seedRhManagerWithEndpoint(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret',
            'active' => true,
        ]);

        return ['company' => $company, 'rh' => $rh, 'endpoint' => $endpoint];
    }

    public function test_non_principal_manager_gets_403_on_store(): void
    {
        ['rh' => $rh] = $this->seedRhManagerWithEndpoint();
        Sanctum::actingAs($rh);

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
        ])->assertStatus(403);
    }

    public function test_non_principal_manager_gets_403_on_update(): void
    {
        ['rh' => $rh, 'endpoint' => $endpoint] = $this->seedRhManagerWithEndpoint();
        Sanctum::actingAs($rh);

        $this->putJson("/api/v1/webhooks/{$endpoint->id}", [
            'url' => 'https://example.com/hook-v2',
            'events' => ['employee.updated'],
        ])->assertStatus(403);
    }

    public function test_non_principal_manager_gets_403_on_destroy(): void
    {
        ['rh' => $rh, 'endpoint' => $endpoint] = $this->seedRhManagerWithEndpoint();
        Sanctum::actingAs($rh);

        $this->deleteJson("/api/v1/webhooks/{$endpoint->id}")->assertStatus(403);
    }

    public function test_non_principal_manager_gets_403_on_test(): void
    {
        ['rh' => $rh, 'endpoint' => $endpoint] = $this->seedRhManagerWithEndpoint();
        Sanctum::actingAs($rh);

        $this->postJson("/api/v1/webhooks/{$endpoint->id}/test")->assertStatus(403);
    }

    public function test_principal_manager_can_store(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
        ])->assertStatus(201);
    }
}
