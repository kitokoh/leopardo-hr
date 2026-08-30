<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelConversation;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CRM V1 — Canaux de communication (issue #5725/#5727).
 *
 * CRUD des canaux, RBAC (api.manager:principal,rh) et isolation tenant :
 * un canal d'un autre tenant est introuvable (404, jamais 403/200).
 */
class CrmChannelCrudTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var \App\Core\Tenant\Domain\Models\Company $companyA */
        /** @var \App\Core\Tenant\Domain\Models\Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;
        /** @var \App\Core\Tenant\Domain\Models\Company $companyB */
        /** @var \App\Core\Tenant\Domain\Models\Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    public function test_employee_cannot_access_crm_channels(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/crm/channels')->assertStatus(403);
    }

    public function test_manager_rh_can_access_crm_channels(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'rh'));

        $this->getJson('/api/v1/crm/channels')->assertOk();
    }

    public function test_channel_can_be_configured_and_listed(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/channels', [
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'is_configured' => true,
            'monthly_quota' => 500,
            'settings' => [
                'phone_number_id' => '123456789',
                'language_code' => 'fr',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'whatsapp');
        $response->assertJsonPath('data.provider', 'whatsapp_cloud_api');
        $response->assertJsonPath('data.monthly_quota', 500);

        $this->getJson('/api/v1/crm/channels')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_secret_fields_are_rejected_in_channel_payload(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/channels', [
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'settings' => ['token' => 'super-secret'],
        ])->assertStatus(422);
    }

    public function test_unknown_channel_type_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/channels', [
            'type' => 'telepathy',
            'provider' => 'x',
        ])->assertStatus(422);
    }

    public function test_other_tenant_channel_is_not_visible(): void
    {
        $channel = CrmChannel::query()->create([
            'company_id' => $this->companyB->id,
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/crm/channels')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/crm/channels/'.$channel->id.'/messages')->assertStatus(404);
        $this->getJson('/api/v1/crm/channels/'.$channel->id.'/conversations')->assertStatus(404);
    }

    public function test_messages_are_listed_without_pii(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $channel = CrmChannel::query()->create([
            'company_id' => $this->companyA->id,
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
        ]);

        CrmChannelMessage::query()->create([
            'channel_id' => $channel->id,
            'company_id' => $this->companyA->id,
            'provider' => 'whatsapp_cloud_api',
            'direction' => 'outbound',
            'to_address' => '+213555010203',
            'body' => 'Bonjour {1}',
            'status' => 'sent',
        ]);

        $this->getJson('/api/v1/crm/channels/'.$channel->id.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'outbound')
            ->assertJsonPath('data.0.status', 'sent')
            // PII masquée : jamais de corps ni d'adresse en clair.
            ->assertJsonMissingPath('data.0.body')
            ->assertJsonMissingPath('data.0.to_address');
    }

    public function test_conversations_are_tenant_scoped_and_listed(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $channel = CrmChannel::query()->create([
            'company_id' => $this->companyA->id,
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
        ]);

        CrmChannelConversation::query()->create([
            'channel_id' => $channel->id,
            'company_id' => $this->companyA->id,
            'provider_conversation_id' => 'conv-a',
            'status' => 'open',
        ]);

        $this->getJson('/api/v1/crm/channels/'.$channel->id.'/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'open');
    }
}
