<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Application\Services\CrmEmailService;
use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use App\Modules\CRM\Infrastructure\Services\UnsubscribeTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5726 — email transactionnel et marketing contrôlé.
 *
 * Couvre : fournisseur interchangeable, suppression (bounce/complaint/
 * désabonnement) respectée avant envoi, aucun message marketing sans
 * consentement requis, quotas par tenant/heure (429), webhook signé,
 * audit des envois, RBAC.
 */
class CrmEmailTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function fakeConsentChecker(bool $allowed): CampaignConsentCheckerInterface
    {
        return new class($allowed) implements CampaignConsentCheckerInterface
        {
            public function __construct(private readonly bool $allowed) {}

            public function allows(int $contactId, string $channel): bool
            {
                return $this->allowed;
            }
        };
    }

    private function emailService(): CrmEmailService
    {
        return $this->app->make(CrmEmailService::class);
    }

    private function ensureContactsTable(): void
    {
        if (Schema::hasTable('crm_contacts')) {
            return;
        }

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('email', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    private function ensureCampaignSendsTable(): void
    {
        if (Schema::hasTable('crm_campaign_sends')) {
            return;
        }

        // Table livrée par la migration de l'issue #5724 : créée ad-hoc tant
        // que la PR #5764 n'est pas mergée (schéma aligné).
        Schema::create('crm_campaign_sends', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('contact_id');
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id', 255)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    private function ensureConsentsTable(): void
    {
        if (Schema::hasTable('crm_consents')) {
            return;
        }

        Schema::create('crm_consents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('contact_id');
            $table->string('channel', 20);
            $table->string('purpose', 20);
            $table->string('status', 20);
            $table->string('source', 30)->default('manual');
            $table->string('source_ref', 255)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'contact_id', 'channel', 'purpose']);
        });
    }

    // ─── Envoi transactionnel ───────────────────────────────────────────────

    public function test_transactional_send_via_log_provider(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/email/transactional', [
            'to' => 'client@example.com',
            'subject' => 'Votre facture',
            'body' => 'Bonjour, votre facture est disponible.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'sent');
        $this->assertNotNull($response->json('data.message_id'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'module' => 'crm-email',
            'action' => 'email.sent',
        ]);
    }

    public function test_transactional_send_respects_suppression(): void
    {
        $provider = new RecordingEmailProvider;
        $this->app->instance(EmailProviderInterface::class, $provider);
        $this->emailService()->suppress($this->companyA->id, 'supprime@example.com', 'bounce', 'webhook');

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/email/transactional', [
            'to' => 'SUPPRIME@example.com',
            'subject' => 'Test',
            'body' => 'Ne doit pas partir.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'suppressed');
        $this->assertCount(0, $provider->calls);
    }

    public function test_company_id_is_prohibited(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/email/transactional', [
            'to' => 'a@example.com',
            'subject' => 'Test',
            'body' => 'x',
            'company_id' => $this->companyB->id,
        ])->assertStatus(422);
    }

    // ─── Envoi marketing : consentement obligatoire ─────────────────────────

    public function test_marketing_send_requires_consent(): void
    {
        // Checker par défaut (fail-closed) : aucun consentement → 422.
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/email/marketing', [
            'contact_id' => 42,
            'to' => 'prospect@example.com',
            'subject' => 'Offre',
            'body' => 'Découvrez notre offre.',
        ])->assertStatus(422);
    }

    public function test_marketing_send_with_consent_succeeds(): void
    {
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker(true));

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/email/marketing', [
            'contact_id' => 42,
            'to' => 'prospect@example.com',
            'subject' => 'Offre',
            'body' => 'Découvrez notre offre.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'sent');
    }

    public function test_marketing_rate_limit_exceeded_returns_429(): void
    {
        config(['crm.email.rate_limit_per_hour' => 1]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker(true));

        Sanctum::actingAs($this->manager($this->companyA));

        $payload = ['contact_id' => 42, 'to' => 'prospect@example.com', 'subject' => 'Offre', 'body' => 'Contenu'];

        $this->postJson('/api/v1/crm/email/marketing', $payload)->assertStatus(200);
        $this->postJson('/api/v1/crm/email/marketing', $payload)->assertStatus(429);
    }

    // ─── Fournisseur interchangeable ────────────────────────────────────────

    public function test_provider_is_interchangeable(): void
    {
        $provider = new RecordingEmailProvider;
        $this->app->instance(EmailProviderInterface::class, $provider);

        $result = $this->emailService()->sendTransactional(
            new EmailMessage('a@example.com', 'Sujet', 'Corps'),
            $this->companyA->id,
        );

        $this->assertTrue($result->isDelivered());
        $this->assertSame('recorder', $this->emailService()->providerName());
        $this->assertCount(1, $provider->calls);
        $this->assertSame('a@example.com', $provider->calls[0]->to);
    }

    // ─── Désabonnement (jeton signé) ───────────────────────────────────────

    public function test_unsubscribe_flow_revokes_consent_and_suppresses(): void
    {
        $this->ensureConsentsTable();
        DB::table('crm_consents')->insert([
            'company_id' => $this->companyA->id,
            'contact_id' => 77,
            'channel' => 'email',
            'purpose' => 'marketing',
            'status' => 'granted',
            'source' => 'manual',
            'granted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->app->make(UnsubscribeTokenService::class)
            ->issue($this->companyA->id, 77, 'se-desabonner@example.com');

        $response = $this->postJson('/api/v1/crm/email/unsubscribe', ['token' => $token]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'unsubscribed');

        $hash = hash('sha256', 'se-desabonner@example.com');
        $this->assertDatabaseHas('crm_email_suppressions', [
            'company_id' => $this->companyA->id,
            'email_hash' => $hash,
            'reason' => 'unsubscribe',
        ]);
        $this->assertDatabaseHas('crm_consents', [
            'company_id' => $this->companyA->id,
            'contact_id' => 77,
            'channel' => 'email',
            'purpose' => 'marketing',
            'status' => 'withdrawn',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'email.unsubscribed',
        ]);
    }

    public function test_unsubscribe_invalid_token_is_rejected(): void
    {
        $this->postJson('/api/v1/crm/email/unsubscribe', ['token' => 'faux-token'])
            ->assertStatus(422);
    }

    // ─── Webhook (bounce / complaint) ───────────────────────────────────────

    public function test_webhook_bounce_adds_suppression_and_marks_send(): void
    {
        config(['crm.email.webhook_secret' => 'test-secret']);
        $this->ensureCampaignSendsTable();

        app()->instance('current_company', $this->companyA);
        /** @var CrmCampaignSend $send */
        $send = CrmCampaignSend::query()->create([
            'campaign_id' => 1,
            'contact_id' => 9,
            'channel' => 'email',
            'status' => 'sent',
            'provider_message_id' => 'msg-123',
        ]);

        $response = $this->postJson('/api/v1/crm/email/webhook', [
            'company_id' => $this->companyA->id,
            'event' => 'bounced',
            'message_id' => 'msg-123',
            'email' => 'rebond@example.com',
        ], ['X-Leopardo-Webhook-Secret' => 'test-secret']);

        $response->assertStatus(200);

        $this->assertDatabaseHas('crm_email_suppressions', [
            'company_id' => $this->companyA->id,
            'email_hash' => hash('sha256', 'rebond@example.com'),
            'reason' => 'bounced',
        ]);
        $this->assertDatabaseHas('crm_campaign_sends', [
            'id' => $send->id,
            'status' => 'bounced',
        ]);
        $this->assertDatabaseHas('crm_email_events', [
            'company_id' => $this->companyA->id,
            'event' => 'bounced',
            'provider_message_id' => 'msg-123',
        ]);
    }

    public function test_webhook_wrong_secret_is_forbidden(): void
    {
        config(['crm.email.webhook_secret' => 'test-secret']);

        $this->postJson('/api/v1/crm/email/webhook', [
            'company_id' => $this->companyA->id,
            'event' => 'bounced',
        ], ['X-Leopardo-Webhook-Secret' => 'mauvais'])->assertStatus(403);
    }

    public function test_webhook_without_secret_is_forbidden(): void
    {
        config(['crm.email.webhook_secret' => '']);

        $this->postJson('/api/v1/crm/email/webhook', [
            'company_id' => $this->companyA->id,
            'event' => 'sent',
        ])->assertStatus(403);
    }

    // ─── Envoi de campagne (#5724) ──────────────────────────────────────────

    public function test_send_campaign_send_resolves_contact_and_marks_sent(): void
    {
        $this->ensureContactsTable();
        $this->ensureCampaignSendsTable();
        app()->instance('current_company', $this->companyA);

        DB::table('crm_contacts')->insert([
            'company_id' => $this->companyA->id,
            'id' => 5,
            'email' => 'contact-campagne@example.com',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var CrmCampaignSend $send */
        $send = CrmCampaignSend::query()->create([
            'campaign_id' => 1,
            'contact_id' => 5,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $result = $this->emailService()->sendCampaignSend($send, $this->companyA->id);

        $this->assertTrue($result->isDelivered());
        $send->refresh();
        $this->assertSame('sent', $send->status);
        $this->assertNotNull($send->provider_message_id);
    }

    // ─── RBAC ───────────────────────────────────────────────────────────────

    public function test_ordinary_employee_is_forbidden(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->postJson('/api/v1/crm/email/transactional', [
            'to' => 'a@example.com',
            'subject' => 'Test',
            'body' => 'x',
        ])->assertStatus(403);
    }

    public function test_comptable_cannot_send_marketing(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->postJson('/api/v1/crm/email/marketing', [
            'contact_id' => 42,
            'to' => 'a@example.com',
            'subject' => 'Test',
            'body' => 'x',
        ])->assertStatus(403);
    }
}

/**
 * Provider d'enregistrement (interchangeabilité) — Issue #5726.
 *
 * @internal
 */
final class RecordingEmailProvider implements EmailProviderInterface
{
    /** @var list<EmailMessage> */
    public array $calls = [];

    public function send(EmailMessage $message): EmailDeliveryResult
    {
        $this->calls[] = $message;

        return EmailDeliveryResult::sent('recorder-'.count($this->calls));
    }

    public function providerName(): string
    {
        return 'recorder';
    }
}
