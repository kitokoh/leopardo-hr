<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelConversation;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use App\Modules\CRM\Domain\Models\CrmWebhookChannelLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Log;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CRM V1 — Webhook WhatsApp Business Cloud API (issue #5725).
 *
 * Vérification d'abonnement, signature HMAC fail-closed, résolution du
 * tenant via lookup public, idempotence (rejeu absorbé), inbox unique et
 * mises à jour de statut de livraison.
 */
class CrmWhatsAppWebhookTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SECRET = 'webhook-secret-123';

    private Company $company;

    private CrmChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crm.webhooks.shared_secret', self::SECRET);
        config()->set('crm.webhooks.whatsapp_verify_token', 'verify-abc');

        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->channel = CrmChannel::query()->create([
            'company_id' => $this->company->id,
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
            'settings' => ['phone_number_id' => '987654321', 'language_code' => 'fr'],
        ]);

        CrmWebhookChannelLookup::query()->create([
            'company_id' => $this->company->id,
            'channel_id' => $this->channel->id,
            'provider' => 'whatsapp',
            'provider_key' => '987654321',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    public function test_subscription_verification_accepts_valid_token(): void
    {
        $this->getJson('/api/v1/crm/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-abc&hub_challenge=CHALLENGE123')
            ->assertOk()
            ->assertSee('CHALLENGE123');
    }

    public function test_subscription_verification_rejects_invalid_token(): void
    {
        $this->getJson('/api/v1/crm/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=x')
            ->assertStatus(403);
    }

    public function test_webhook_is_fail_closed_without_secret(): void
    {
        config()->set('crm.webhooks.shared_secret', '');

        $this->postJson('/api/v1/crm/webhooks/whatsapp', ['entry' => []])
            ->assertStatus(503);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->postJson('/api/v1/crm/webhooks/whatsapp', ['entry' => []], [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ])->assertStatus(401);
    }

    public function test_inbound_message_is_persisted_tenant_scoped(): void
    {
        $payload = $this->messagePayload('wamid.INBOUND1', '+213600000001', 'Salut !');

        $this->postSigned($payload)->assertOk()->assertJsonPath('received', true);

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'provider_message_id' => 'wamid.INBOUND1',
            'direction' => 'inbound',
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('crm_channel_conversations', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_webhook_replay_is_idempotent(): void
    {
        $payload = $this->messagePayload('wamid.REPLAY1', '+213600000002', 'Hello');

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk();

        $this->assertSame(1, CrmChannelMessage::query()
            ->where('company_id', $this->company->id)
            ->where('provider_message_id', 'wamid.REPLAY1')
            ->count());
    }

    public function test_delivery_status_updates_are_applied(): void
    {
        $payload = $this->messagePayload('wamid.OUT1', '+213600000003', 'Hi');
        $this->postSigned($payload)->assertOk();

        $statusPayload = [
            'entry' => [[
                'id' => 'PHONE_ID',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => '987654321'],
                        'statuses' => [[
                            'id' => 'wamid.OUT1',
                            'status' => 'delivered',
                            'timestamp' => '1720000000',
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $this->postSigned($statusPayload)->assertOk();

        $this->assertDatabaseHas('crm_channel_messages', [
            'company_id' => $this->company->id,
            'provider_message_id' => 'wamid.OUT1',
            'status' => 'delivered',
        ]);
    }

    public function test_unknown_phone_number_id_is_ignored_without_crash(): void
    {
        $payload = [
            'entry' => [[
                'id' => 'OTHER',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => '000000000'],
                        'messages' => [[
                            'from' => '+213600000099',
                            'id' => 'wamid.UNKNOWN1',
                            'timestamp' => '1720000000',
                            'type' => 'text',
                            'text' => ['body' => 'x'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        Log::spy();

        $this->postSigned($payload)->assertOk();

        $this->assertDatabaseMissing('crm_channel_messages', [
            'provider_message_id' => 'wamid.UNKNOWN1',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postSigned(array $payload): TestResponse
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $raw, self::SECRET);

        return $this->call('POST', '/api/v1/crm/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'ACCEPT' => 'application/json',
        ], $raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(string $messageId, string $from, string $body): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'PHONE_ID',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => '987654321'],
                        'contacts' => [['profile' => ['name' => 'Client'], 'wa_id' => $from]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' => '1720000000',
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }
}
