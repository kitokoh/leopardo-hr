<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Events\CampaignStarted;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5724 — campagnes marketing tenant.
 *
 * Couvre : cycle de vie (draft → running → paused/resume → finished |
 * cancelled, transitions invalides 422), audience segment OU explicite,
 * filtre de consentement au start (fail-closed), envoi stoppable,
 * report par statut, RBAC, isolation tenant, audit.
 */
class CrmCampaignTest extends TestCase
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

    /**
     * @param  list<int>  $allowed
     */
    private function fakeConsentChecker(array $allowed): CampaignConsentCheckerInterface
    {
        return new class($allowed) implements CampaignConsentCheckerInterface
        {
            /**
             * @param  list<int>  $allowed
             */
            public function __construct(private readonly array $allowed) {}

            public function allows(int $contactId, string $channel): bool
            {
                return in_array($contactId, $this->allowed, true);
            }
        };
    }

    /**
     * @param  list<int>  $audience
     */
    private function createCampaign(array $audience = [500], string $channel = 'email'): CrmCampaign
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmCampaign $campaign */
        $campaign = CrmCampaign::query()->create([
            'name' => 'Campagne test',
            'channel' => $channel,
            'status' => 'draft',
            'audience_snapshot' => $audience === [] ? null : $audience,
        ]);

        return $campaign;
    }

    private function ensureSegmentMembersTable(): void
    {
        if (Schema::hasTable('crm_segment_members')) {
            return;
        }

        Schema::create('crm_segment_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('segment_id');
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('contact_id');
            $table->string('source', 20)->default('computed');
            $table->timestamp('built_at')->nullable();
            $table->timestamps();
            $table->unique(['segment_id', 'contact_id']);
        });
    }

    // ─── Création / cycle de vie ────────────────────────────────────────────

    public function test_principal_can_create_draft_campaign(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/campaigns', [
            'name' => 'Campagne email Q3',
            'channel' => 'email',
            'audience' => [10, 11, 12],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Campagne email Q3');
        $response->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('crm_campaigns', [
            'company_id' => $this->companyA->id,
            'name' => 'Campagne email Q3',
            'status' => 'draft',
        ]);
    }

    public function test_segment_and_explicit_audience_are_mutually_exclusive(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/campaigns', [
            'name' => 'Campagne ambiguë',
            'channel' => 'email',
            'segment_id' => 3,
            'audience' => [10],
        ])->assertStatus(422);
    }

    public function test_status_field_is_prohibited(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/campaigns', [
            'name' => 'Campagne fourbe',
            'channel' => 'email',
            'audience' => [10],
            'status' => 'running',
        ])->assertStatus(422);
    }

    // ─── Démarrage et consentement ──────────────────────────────────────────

    public function test_start_filters_audience_by_consent(): void
    {
        $campaign = $this->createCampaign([100, 101, 102]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker([100, 102]));

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start");
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'running');

        $campaign->refresh();

        // Seuls les contacts consentants ont un envoi.
        $this->assertSame([100, 102], $campaign->audience_snapshot);
        $this->assertSame(2, CrmCampaignSend::query()->where('campaign_id', $campaign->id)->count());
        $this->assertDatabaseHas('crm_campaign_sends', ['campaign_id' => $campaign->id, 'contact_id' => 100]);
        $this->assertDatabaseMissing('crm_campaign_sends', ['campaign_id' => $campaign->id, 'contact_id' => 101]);
    }

    public function test_start_is_fail_closed_without_consent_table(): void
    {
        $campaign = $this->createCampaign([100, 101]);

        Sanctum::actingAs($this->manager($this->companyA));

        // Checker par défaut : table crm_consents absente → aucun envoi.
        $response = $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start");
        $response->assertStatus(200);

        $this->assertSame(0, CrmCampaignSend::query()->where('campaign_id', $campaign->id)->count());
        $this->assertDatabaseHas('crm_campaigns', [
            'id' => $campaign->id,
            'status' => 'running',
        ]);
    }

    public function test_start_with_segment_uses_segment_members(): void
    {
        $this->ensureSegmentMembersTable();

        $campaign = $this->createCampaign([]);
        $campaign->update(['segment_id' => 77]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker([201, 202, 203]));

        \Illuminate\Support\Facades\DB::table('crm_segment_members')->insert([
            ['segment_id' => 77, 'company_id' => $this->companyA->id, 'contact_id' => 201, 'source' => 'computed'],
            ['segment_id' => 77, 'company_id' => $this->companyA->id, 'contact_id' => 202, 'source' => 'computed'],
            ['segment_id' => 77, 'company_id' => $this->companyB->id, 'contact_id' => 999, 'source' => 'computed'],
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        // Membre du tenant B exclu (isolation), les deux du tenant A inclus.
        $this->assertSame(2, CrmCampaignSend::query()->where('campaign_id', $campaign->id)->count());
        $this->assertDatabaseMissing('crm_campaign_sends', ['campaign_id' => $campaign->id, 'contact_id' => 999]);
    }

    public function test_start_empty_segment_returns_422(): void
    {
        $this->ensureSegmentMembersTable();

        $campaign = $this->createCampaign([]);
        $campaign->update(['segment_id' => 99]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(422);
    }

    public function test_start_dispatches_campaign_started_event(): void
    {
        Event::fake();
        $campaign = $this->createCampaign([100]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker([100]));

        Sanctum::actingAs($this->manager($this->companyA));
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        Event::assertDispatched(CampaignStarted::class, function (CampaignStarted $event) use ($campaign): bool {
            return $event->campaignId === $campaign->id
                && $event->channel === 'email'
                && $event->audienceSize === 1;
        });
    }

    // ─── Pause / resume / cancel / finish ───────────────────────────────────

    public function test_pause_resume_lifecycle(): void
    {
        $campaign = $this->createCampaign([100]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker([100]));

        Sanctum::actingAs($this->manager($this->companyA));
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/pause")->assertJsonPath('data.status', 'paused');
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/resume")->assertJsonPath('data.status', 'running');

        // Transitions invalides.
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/pause")->assertStatus(200); // running → paused OK
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/pause")->assertStatus(422); // paused → paused KO
    }

    public function test_cancel_cancels_pending_sends(): void
    {
        $campaign = $this->createCampaign([100, 101]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker([100, 101]));

        Sanctum::actingAs($this->manager($this->companyA));
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/cancel")->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(2, CrmCampaignSend::query()->where('campaign_id', $campaign->id)->where('status', 'cancelled')->count());
        $this->assertDatabaseHas('crm_campaigns', ['id' => $campaign->id, 'finished_at' => now()]);
    }

    public function test_report_counts_sends_by_status(): void
    {
        $campaign = $this->createCampaign([100, 101, 102]);
        $this->app->instance(CampaignConsentCheckerInterface::class, $this->fakeConsentChecker([100, 101, 102]));

        Sanctum::actingAs($this->manager($this->companyA));
        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        CrmCampaignSend::query()->where('campaign_id', $campaign->id)->where('contact_id', 100)->update(['status' => 'sent', 'sent_at' => now()]);
        CrmCampaignSend::query()->where('campaign_id', $campaign->id)->where('contact_id', 101)->update(['status' => 'failed', 'error' => 'provider 500']);

        $response = $this->getJson("/api/v1/crm/campaigns/{$campaign->id}/report");
        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 3);
        $response->assertJsonPath('data.pending', 1);
        $response->assertJsonPath('data.sent', 1);
        $response->assertJsonPath('data.failed', 1);
    }

    // ─── RBAC & isolation ───────────────────────────────────────────────────

    public function test_ordinary_employee_is_forbidden(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/crm/campaigns')->assertStatus(403);
    }

    public function test_comptable_cannot_start_campaign(): void
    {
        $campaign = $this->createCampaign([100]);

        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(403);
    }

    public function test_cross_tenant_campaign_is_404(): void
    {
        app()->instance('current_company', $this->companyB);

        /** @var CrmCampaign $campaignB */
        $campaignB = CrmCampaign::query()->create([
            'name' => 'Campagne B',
            'channel' => 'email',
            'status' => 'draft',
            'audience_snapshot' => [10],
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson("/api/v1/crm/campaigns/{$campaignB->id}")->assertStatus(404);
        $this->postJson("/api/v1/crm/campaigns/{$campaignB->id}/start")->assertStatus(404);
        $this->getJson("/api/v1/crm/campaigns/{$campaignB->id}/report")->assertStatus(404);
    }

    public function test_destroy_removes_sends(): void
    {
        $campaign = $this->createCampaign([100]);
        CrmCampaignSend::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => 100,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->deleteJson("/api/v1/crm/campaigns/{$campaign->id}")->assertStatus(204);

        $this->assertDatabaseMissing('crm_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('crm_campaign_sends', ['campaign_id' => $campaign->id]);
    }
}
