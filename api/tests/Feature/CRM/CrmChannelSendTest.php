<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CRM V1 — Envoi de messages WhatsApp Business (issue #5725).
 *
 * Consentement (fail-closed par défaut), quotas, envoi provider (Http fake),
 * 429/5xx → retry borné + dead-letter, jamais de retry infini.
 */
class CrmChannelSendTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function whatsappChannel(array $overrides = []): CrmChannel
    {
        /** @var CrmChannel $channel */
        $channel = CrmChannel::query()->create(array_merge([
            'company_id' => $this->company->id,
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
            'settings' => ['phone_number_id' => '123456789', 'language_code' => 'fr'],
        ], $overrides));

        return $channel;
    }

    private function fakeProviderSuccess(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.ABC123']],
            ], 200),
        ]);
    }

    public function test_consent_is_required_by_default_when_contact_is_known(): void
    {
        config()->set('crm.channels.consent_fallback', 'deny');
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();
        $this->fakeProviderSuccess();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Bonjour !',
            'contact_id' => 'contact-123',
        ])->assertStatus(422)->assertJsonPath('error', 'CRM_CONSENT_REQUIRED');

        $this->assertDatabaseMissing('crm_channel_messages', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_message_is_sent_with_consent_fallback_allow(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();
        $this->fakeProviderSuccess();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Bonjour !',
        ])->assertCreated()->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.ABC123',
        ]);
    }

    public function test_template_message_is_sent(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();
        $this->fakeProviderSuccess();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'template_name' => 'welcome',
            'template_parameters' => ['Alice'],
        ])->assertCreated()->assertJsonPath('data.template_name', 'welcome');
    }

    public function test_quota_exceeded_returns_429(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel(['monthly_quota' => 1]);
        $this->fakeProviderSuccess();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Un',
        ])->assertCreated();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Deux',
        ])->assertStatus(429)->assertJsonPath('error', 'CRM_QUOTA_EXCEEDED');
    }

    public function test_provider_500_dead_letters_after_max_attempts(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        config()->set('crm.channels.max_attempts', 1);
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'boom', 'code' => 190]], 500),
        ]);

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Bonjour',
        ])->assertStatus(502)->assertJsonPath('error', 'CRM_PROVIDER_ERROR');

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'status' => 'dead_lettered',
        ]);
    }

    public function test_provider_429_is_retryable_then_dead_letters(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        config()->set('crm.channels.max_attempts', 2);
        config()->set('crm.channels.retry_backoff_seconds', 0);
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'rate limited', 'code' => 130429]], 429),
        ]);

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'body' => 'Bonjour',
        ])->assertStatus(502);

        $message = CrmChannelMessage::query()
            ->where('company_id', $this->company->id)
            ->firstOrFail();

        // 1er échec : statut failed retryable (pas dead-letter), job dispatché.
        $this->assertSame('failed', $message->status);
        $this->assertSame(1, $message->attempts);

        // Retry via le service : second échec → dead-letter (tentatives épuisées).
        $service = app(\App\Modules\CRM\Infrastructure\Services\CrmChannelService::class);
        $service->retry((string) $message->id);

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'status' => 'dead_lettered',
        ]);
    }

    public function test_invalid_destination_is_rejected_without_provider_call(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();
        Http::fake();

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => 'pas-un-numero',
            'body' => 'Bonjour',
        ])->assertStatus(502)->assertJsonPath('error', 'CRM_PROVIDER_ERROR');

        Http::assertNothingSent();
    }

    public function test_provider_400_is_immediately_dead_lettered(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $channel = $this->whatsappChannel();

        // 4xx métier (ex. template non approuvé) → non retryable → dead-letter
        // immédiat, quel que soit max_attempts.
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'template not approved', 'code' => 131047],
            ], 400),
        ]);

        $this->postJson('/api/v1/crm/channels/'.$channel->id.'/send', [
            'to' => '+213555010203',
            'template_name' => 'non_approved_template',
        ])->assertStatus(502);

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'status' => 'dead_lettered',
        ]);
    }
}
