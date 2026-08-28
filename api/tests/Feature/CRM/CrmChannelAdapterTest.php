<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use App\Modules\CRM\Infrastructure\Integrations\Sms\SmsAdapter;
use App\Modules\CRM\Infrastructure\Integrations\WhatsApp\WhatsAppAdapter;
use App\Modules\CRM\Infrastructure\Services\CrmChannelRegistry;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CRM V1 — Canaux par adaptateur (issue #5727).
 *
 * Contrat commun send/verify/normalize/revoke implémenté par chaque
 * provider, SMS en provider audit-only (aucun appel HTTP, pas de PII en
 * log), consentement + quotas par tenant, coûts/erreurs observables.
 */
class CrmChannelAdapterTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $this->company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    public function test_registry_exposes_whatsapp_and_sms_adapters(): void
    {
        /** @var CrmChannelRegistry $registry */
        $registry = app(CrmChannelRegistry::class);

        $this->assertSame(['whatsapp', 'sms'], $registry->availableTypes());
        $this->assertInstanceOf(WhatsAppAdapter::class, $registry->adapterFor(CrmChannelType::WHATSAPP));
        $this->assertInstanceOf(SmsAdapter::class, $registry->adapterFor(CrmChannelType::SMS));
    }

    public function test_every_adapter_implements_the_channel_contract(): void
    {
        /** @var CrmChannelRegistry $registry */
        $registry = app(CrmChannelRegistry::class);

        foreach ($registry->availableTypes() as $type) {
            $adapter = $registry->adapterFor($type);
            $this->assertInstanceOf(ChannelAdapterContract::class, $adapter);
            $this->assertSame($type, $adapter->channelType());

            // normalize → verify round-trip sur un numéro E.164 valide
            $normalized = $adapter->normalize('+213 555 01 02 03');
            $this->assertNotNull($normalized);
            $this->assertTrue($adapter->verify((string) $normalized, []));

            // numéro invalide → normalize null, verify false
            $this->assertNull($adapter->normalize('pas-un-numero'));
            $this->assertFalse($adapter->verify('pas-un-numero', []));
        }
    }

    public function test_sms_send_is_audit_only_and_persists_message(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $channel = CrmChannel::query()->create([
            'company_id' => $this->company->id,
            'type' => 'sms',
            'provider' => 'sms_audit',
            'status' => 'active',
            'is_configured' => true,
        ]);

        Http::fake(); // aucun appel externe ne doit partir

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Votre code est 1234',
        ])->assertCreated()->assertJsonPath('data.status', 'sent');

        Http::assertNothingSent();

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'channel_id' => $channel->id,
            'status' => 'sent',
        ]);
    }

    public function test_sms_send_respects_consent_and_quota(): void
    {
        config()->set('crm.channels.consent_fallback', 'deny');
        Sanctum::actingAs($this->manager());

        $channel = CrmChannel::query()->create([
            'company_id' => $this->company->id,
            'type' => 'sms',
            'provider' => 'sms_audit',
            'status' => 'active',
            'is_configured' => true,
            'monthly_quota' => 1,
        ]);

        // Consentement refusé (contact connu, fallback deny) → 422, aucun message.
        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'spam',
            'contact_id' => 'contact-9',
        ])->assertStatus(422)->assertJsonPath('error', 'CRM_CONSENT_REQUIRED');

        // Quota 1 : premier envoi OK, second → 429.
        config()->set('crm.channels.consent_fallback', 'allow');
        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'un',
        ])->assertCreated();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'deux',
        ])->assertStatus(429)->assertJsonPath('error', 'CRM_QUOTA_EXCEEDED');
    }

    public function test_observability_aggregates_costs_and_errors(): void
    {
        Sanctum::actingAs($this->manager());

        $channel = CrmChannel::query()->create([
            'company_id' => $this->company->id,
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
        ]);

        CrmChannelMessage::query()->create([
            'company_id' => $this->company->id,
            'channel_id' => $channel->id,
            'provider' => 'whatsapp_cloud_api',
            'direction' => 'outbound',
            'status' => 'sent',
            'cost' => 0.25,
        ]);
        CrmChannelMessage::query()->create([
            'company_id' => $this->company->id,
            'channel_id' => $channel->id,
            'provider' => 'whatsapp_cloud_api',
            'direction' => 'outbound',
            'status' => 'dead_lettered',
            'cost' => 0.0,
            'attempts' => 3,
        ]);

        $this->getJson('/api/v1/crm/channels/'.$channel->id.'/observability')
            ->assertOk()
            ->assertJsonPath('data.total_messages', 2)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.dead_lettered', 1)
            ->assertJsonPath('data.total_attempts', 3)
            ->assertJsonPath('data.total_cost', 0.25);
    }

    public function test_other_tenant_observability_is_forbidden(): void
    {
        Sanctum::actingAs($this->manager());

        $channel = CrmChannel::query()->create([
            'company_id' => Company::factory()->create(['country' => 'MA', 'currency' => 'MAD'])->id,
            'type' => 'sms',
            'provider' => 'sms_audit',
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/crm/channels/'.$channel->id.'/observability')
            ->assertStatus(404);
    }
}
