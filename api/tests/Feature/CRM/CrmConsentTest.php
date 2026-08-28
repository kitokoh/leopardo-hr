<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Application\Services\CommunicationConsentService;
use App\Modules\CRM\Domain\Enums\ConsentChannel;
use App\Modules\CRM\Domain\Enums\ConsentPurpose;
use App\Modules\CRM\Domain\Enums\ConsentSource;
use App\Modules\CRM\Domain\Enums\ConsentStatus;
use App\Modules\CRM\Domain\Events\CrmConsentRevoked;
use App\Modules\CRM\Domain\Models\CrmConsent;
use App\Modules\CRM\Infrastructure\Services\CampaignConsentRevocationHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5722 — consentements et préférences de communication CRM.
 *
 * Couvre : accord/refus/retrait tracés (audit immuable), garde d'envoi
 * `allows()` (aucun envoi sans consentement requis), propagation du retrait
 * aux envois de campagne en attente, isolation tenant via l'API (404
 * cross-tenant) et RBAC (principal/marketing en écriture, tout manager en
 * lecture, employé ordinaire refusé).
 */
class CrmConsentTest extends TestCase
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

    private function service(): CommunicationConsentService
    {
        return $this->app->make(CommunicationConsentService::class);
    }

    // ─── Service : cycle de vie du consentement ─────────────────────────────

    public function test_grant_then_allows_returns_true(): void
    {
        app()->instance('current_company', $this->companyA);

        $this->service()->grant(
            42,
            ConsentChannel::Email,
            ConsentPurpose::Marketing,
            ConsentSource::Form,
            'lead-capture-homepage',
        );

        $this->assertTrue($this->service()->allows(42, ConsentChannel::Email, ConsentPurpose::Marketing));
        $this->assertTrue($this->service()->allowsMarketing(42, ConsentChannel::Email));
        $this->assertTrue($this->service()->hasAnyMarketingConsent(42));
    }

    public function test_no_consent_means_no_send(): void
    {
        app()->instance('current_company', $this->companyA);

        // Absence de ligne = refus (fail-closed) : jamais d'envoi marketing.
        $this->assertFalse($this->service()->allows(7, ConsentChannel::Email, ConsentPurpose::Marketing));
        $this->assertFalse($this->service()->hasAnyMarketingConsent(7));
    }

    public function test_deny_blocks_marketing_sends(): void
    {
        app()->instance('current_company', $this->companyA);

        $this->service()->deny(9, ConsentChannel::Email, ConsentPurpose::Marketing, ConsentSource::Manual);

        $this->assertFalse($this->service()->allows(9, ConsentChannel::Email, ConsentPurpose::Marketing));
    }

    public function test_withdraw_revokes_and_dispatches_event(): void
    {
        app()->instance('current_company', $this->companyA);
        Event::fake();

        $this->service()->grant(10, ConsentChannel::Sms, ConsentPurpose::Marketing, ConsentSource::Form);
        $consent = $this->service()->withdraw(
            10,
            ConsentChannel::Sms,
            ConsentPurpose::Marketing,
            ConsentSource::Manual,
            'retrait client par téléphone',
        );

        $this->assertSame(ConsentStatus::Withdrawn->value, $consent->status);
        $this->assertNotNull($consent->revoked_at);
        $this->assertFalse($this->service()->allows(10, ConsentChannel::Sms, ConsentPurpose::Marketing));

        Event::assertDispatched(CrmConsentRevoked::class, function (CrmConsentRevoked $event): bool {
            return $event->contactId === 10
                && $event->channel === ConsentChannel::Sms->value
                && $event->purpose === ConsentPurpose::Marketing->value;
        });
    }

    public function test_transactional_consent_is_distinct_from_marketing(): void
    {
        app()->instance('current_company', $this->companyA);

        $this->service()->grant(11, ConsentChannel::Email, ConsentPurpose::Transactional, ConsentSource::Api);

        // Le consentement transactionnel n'autorise pas l'envoi marketing.
        $this->assertTrue($this->service()->allows(11, ConsentChannel::Email, ConsentPurpose::Transactional));
        $this->assertFalse($this->service()->allows(11, ConsentChannel::Email, ConsentPurpose::Marketing));
    }

    // ─── Service : audit immuable ───────────────────────────────────────────

    public function test_consent_mutations_are_audited(): void
    {
        app()->instance('current_company', $this->companyA);

        $consent = $this->service()->grant(
            12,
            ConsentChannel::Email,
            ConsentPurpose::Marketing,
            ConsentSource::Form,
        );
        $this->service()->withdraw(12, ConsentChannel::Email, ConsentPurpose::Marketing, ConsentSource::EmailLink);

        $actions = AuditLog::query()
            ->where('module', 'crm')
            ->where('auditable_type', CrmConsent::class)
            ->where('auditable_id', $consent->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame(['consent.granted', 'consent.withdrawn'], $actions);

        /** @var AuditLog $withdrawLog */
        $withdrawLog = AuditLog::query()
            ->where('action', 'consent.withdrawn')
            ->firstOrFail();
        $this->assertSame(ConsentStatus::Withdrawn->value, $withdrawLog->new_values['status']);
        $this->assertSame(ConsentStatus::Granted->value, $withdrawLog->new_values['previous_status']);
        $this->assertSame($this->companyA->id, $withdrawLog->company_id);
    }

    // ─── Propagation du retrait aux campagnes (#5724) ───────────────────────

    public function test_revocation_cancels_pending_campaign_sends(): void
    {
        if (Schema::hasTable('crm_campaign_sends')) {
            Schema::drop('crm_campaign_sends');
        }

        Schema::create('crm_campaign_sends', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        DB::table('crm_campaign_sends')->insert([
            [
                'company_id' => $this->companyA->id,
                'campaign_id' => 1,
                'contact_id' => 13,
                'channel' => 'email',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $this->companyA->id,
                'campaign_id' => 1,
                'contact_id' => 13,
                'channel' => 'email',
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $this->companyB->id,
                'campaign_id' => 1,
                'contact_id' => 13,
                'channel' => 'email',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $cancelled = $this->app->make(CampaignConsentRevocationHandler::class)->cancelPendingSends(
            $this->companyA->id,
            13,
            'email',
            'marketing',
        );

        $this->assertSame(1, $cancelled);
        $this->assertDatabaseHas('crm_campaign_sends', [
            'company_id' => $this->companyA->id,
            'contact_id' => 13,
            'status' => 'cancelled',
        ]);
        // Le tenant B n'est pas touché (isolation cross-tenant).
        $this->assertDatabaseHas('crm_campaign_sends', [
            'company_id' => $this->companyB->id,
            'contact_id' => 13,
            'status' => 'pending',
        ]);
        // Un envoi déjà parti n'est pas annulé.
        $this->assertDatabaseHas('crm_campaign_sends', [
            'company_id' => $this->companyA->id,
            'contact_id' => 13,
            'status' => 'sent',
        ]);
    }

    // ─── API : RBAC ─────────────────────────────────────────────────────────

    public function test_principal_can_grant_consent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/consents', [
            'action' => 'granted',
            'contact_id' => 100,
            'channel' => 'email',
            'purpose' => 'marketing',
            'source' => 'api',
            'source_ref' => 'import-2026-08-28',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.contact_id', 100);
        $response->assertJsonPath('data.channel', 'email');
        $response->assertJsonPath('data.purpose', 'marketing');
        $response->assertJsonPath('data.status', 'granted');

        $this->assertDatabaseHas('crm_consents', [
            'company_id' => $this->companyA->id,
            'contact_id' => 100,
            'channel' => 'email',
            'purpose' => 'marketing',
            'status' => 'granted',
        ]);
    }

    public function test_ordinary_employee_is_forbidden(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $response = $this->getJson('/api/v1/crm/consents');
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_wrong_manager_role_is_forbidden_on_write(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $response = $this->postJson('/api/v1/crm/consents', [
            'action' => 'granted',
            'contact_id' => 100,
            'channel' => 'email',
            'purpose' => 'marketing',
            'source' => 'api',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'INSUFFICIENT_ROLE');
    }

    public function test_manager_can_list_consents(): void
    {
        app()->instance('current_company', $this->companyA);
        $this->service()->grant(101, ConsentChannel::Email, ConsentPurpose::Marketing, ConsentSource::Api);

        Sanctum::actingAs($this->manager($this->companyA, 'superviseur'));

        $response = $this->getJson('/api/v1/crm/consents?channel=email');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.contact_id', 101);
    }

    // ─── API : validation stricte ───────────────────────────────────────────

    public function test_invalid_channel_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/consents', [
            'action' => 'granted',
            'contact_id' => 100,
            'channel' => 'pigeon',
            'purpose' => 'marketing',
            'source' => 'api',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('channel');
    }

    public function test_unknown_fields_are_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/consents', [
            'action' => 'granted',
            'contact_id' => 100,
            'channel' => 'email',
            'purpose' => 'marketing',
            'source' => 'api',
            'company_id' => $this->companyB->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('company_id');
    }

    // ─── API : isolation tenant ─────────────────────────────────────────────

    public function test_cross_tenant_consent_is_404(): void
    {
        app()->instance('current_company', $this->companyB);
        /** @var CrmConsent $consentB */
        $consentB = CrmConsent::query()->create([
            'company_id' => $this->companyB->id,
            'contact_id' => 200,
            'channel' => 'email',
            'purpose' => 'marketing',
            'status' => 'granted',
            'source' => 'manual',
            'granted_at' => now(),
        ]);

        // Un manager du tenant A ne peut ni voir ni révoquer le consentement B.
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson("/api/v1/crm/consents/{$consentB->id}")->assertStatus(404);

        $response = $this->postJson("/api/v1/crm/consents/{$consentB->id}/revoke", ['source' => 'manual']);
        $response->assertStatus(404);

        $this->assertDatabaseHas('crm_consents', [
            'id' => $consentB->id,
            'status' => 'granted',
        ]);
    }

    public function test_revoke_via_api_updates_status(): void
    {
        app()->instance('current_company', $this->companyA);
        /** @var CrmConsent $consent */
        $consent = $this->service()->grant(300, ConsentChannel::Email, ConsentPurpose::Marketing, ConsentSource::Form);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson("/api/v1/crm/consents/{$consent->id}/revoke", [
            'source' => 'email_link',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'withdrawn');
        $refreshed = $consent->fresh();
        $response->assertJsonPath('data.revoked_at', $refreshed?->revoked_at?->toIso8601String());
    }
}
