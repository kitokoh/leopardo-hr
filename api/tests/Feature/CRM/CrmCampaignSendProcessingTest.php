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
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use App\Modules\CRM\Infrastructure\Jobs\ProcessCampaignSendsJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7751 — campagnes email : contenu de campagne + envoi effectif.
 *
 * Couvre : sujet/corps obligatoires pour le canal email au start (422),
 * listener `DispatchCampaignSends` (event `CampaignStarted` → job, canal
 * email uniquement), job `ProcessCampaignSendsJob` (message construit depuis
 * le contenu de campagne, statuts sent/failed/suppressed, respect pause,
 * auto-finish, audit), et commande planifiée `crm:process-campaign-sends`
 * (auto-start des scheduled dues + drainage + auto-finish).
 */
class CrmCampaignSendProcessingTest extends TestCase
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

        $this->ensureContactsTable();
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

    /**
     * @param  list<int>  $allowed
     */
    private function allowConsentFor(array $allowed): void
    {
        $this->app->instance(CampaignConsentCheckerInterface::class, new class($allowed) implements CampaignConsentCheckerInterface
        {
            /**
             * @param  list<int>  $allowed
             */
            public function __construct(private readonly array $allowed) {}

            public function allows(int $contactId, string $channel): bool
            {
                return in_array($contactId, $this->allowed, true);
            }
        });
    }

    private function recordingProvider(): CampaignSendRecordingProvider
    {
        $provider = new CampaignSendRecordingProvider;
        $this->app->instance(EmailProviderInterface::class, $provider);

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCampaign(array $attributes = []): CrmCampaign
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmCampaign $campaign */
        $campaign = CrmCampaign::query()->create(array_merge([
            'name' => 'Campagne contenu',
            'channel' => 'email',
            'status' => 'draft',
            'subject' => 'Offre de rentrée',
            'body' => 'Bonjour, découvrez notre offre de rentrée.',
            'audience_snapshot' => [100],
        ], $attributes));

        app()->forgetInstance('current_company');

        return $campaign;
    }

    private function createSend(CrmCampaign $campaign, int $contactId, string $status = 'pending'): CrmCampaignSend
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmCampaignSend $send */
        $send = CrmCampaignSend::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contactId,
            'channel' => 'email',
            'status' => $status,
        ]);

        app()->forgetInstance('current_company');

        return $send;
    }

    private function insertContact(int $id, string $email): void
    {
        DB::table('crm_contacts')->insert([
            'company_id' => $this->companyA->id,
            'id' => $id,
            'first_name' => 'Contact',
            'last_name' => 'N'.$id,
            'email' => $email,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureContactsTable(): void
    {
        if (Schema::hasTable('crm_contacts')) {
            return;
        }

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    // ─── Contenu obligatoire au start (canal email) ─────────────────────────

    public function test_email_campaign_cannot_start_without_subject_and_body(): void
    {
        $campaign = $this->createCampaign(['subject' => null, 'body' => null]);
        $this->allowConsentFor([100]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(422);

        $this->assertSame('draft', $campaign->refresh()->status);
        $this->assertSame(0, CrmCampaignSend::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->count());
    }

    public function test_non_email_campaign_starts_without_content(): void
    {
        Bus::fake([ProcessCampaignSendsJob::class]);
        $campaign = $this->createCampaign(['channel' => 'whatsapp', 'subject' => null, 'body' => null]);
        $this->allowConsentFor([100]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        Bus::assertNotDispatched(ProcessCampaignSendsJob::class);
    }

    public function test_store_accepts_subject_and_body(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/campaigns', [
            'name' => 'Campagne avec contenu',
            'channel' => 'email',
            'audience' => [10],
            'subject' => 'Sujet API',
            'body' => 'Corps API.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.subject', 'Sujet API');
        $response->assertJsonPath('data.body', 'Corps API.');
    }

    // ─── Listener CampaignStarted → job ─────────────────────────────────────

    public function test_starting_email_campaign_queues_processing_job(): void
    {
        Bus::fake([ProcessCampaignSendsJob::class]);

        $campaign = $this->createCampaign();
        $this->allowConsentFor([100]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/start")->assertStatus(200);

        Bus::assertDispatched(ProcessCampaignSendsJob::class, function (ProcessCampaignSendsJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id
                && $job->companyId === $this->companyA->id;
        });
    }

    // ─── Job : envoi effectif ───────────────────────────────────────────────

    public function test_job_sends_campaign_content_and_auto_finishes(): void
    {
        $provider = $this->recordingProvider();

        $this->insertContact(100, 'alpha@example.com');
        $this->insertContact(101, 'beta@example.com');

        $campaign = $this->createCampaign(['status' => 'running', 'started_at' => now()]);
        $this->createSend($campaign, 100);
        $this->createSend($campaign, 101);

        (new ProcessCampaignSendsJob($this->companyA->id, $campaign->id))
            ->handle($this->app->make(CrmEmailService::class));

        $this->assertCount(2, $provider->calls);
        $this->assertSame('Offre de rentrée', $provider->calls[0]->subject);
        $this->assertSame('Bonjour, découvrez notre offre de rentrée.', $provider->calls[0]->body);

        $this->assertSame(2, CrmCampaignSend::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('status', 'sent')->count());

        $campaign->refresh();
        $this->assertSame('finished', $campaign->status);
        $this->assertNotNull($campaign->finished_at);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'module' => 'crm',
            'action' => 'campaign.sends_processed',
        ]);
    }

    public function test_job_marks_suppressed_and_failed_sends(): void
    {
        $this->recordingProvider();

        $this->insertContact(100, 'ok@example.com');
        $this->insertContact(101, 'bloque@example.com');
        // Le contact 102 n'existe pas → failed.

        /** @var CrmEmailService $service */
        $service = $this->app->make(CrmEmailService::class);
        $service->suppress($this->companyA->id, 'bloque@example.com', 'bounce', 'webhook');

        $campaign = $this->createCampaign(['status' => 'running', 'started_at' => now()]);
        $this->createSend($campaign, 100);
        $this->createSend($campaign, 101);
        $this->createSend($campaign, 102);

        (new ProcessCampaignSendsJob($this->companyA->id, $campaign->id))
            ->handle($this->app->make(CrmEmailService::class));

        $sends = CrmCampaignSend::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->orderBy('contact_id')
            ->pluck('status', 'contact_id')
            ->all();

        $this->assertSame('sent', $sends[100]);
        $this->assertSame('suppressed', $sends[101]);
        $this->assertSame('failed', $sends[102]);

        // Plus aucun pending → auto-finish.
        $this->assertSame('finished', $campaign->refresh()->status);
    }

    public function test_job_respects_paused_campaign(): void
    {
        $provider = $this->recordingProvider();

        $this->insertContact(100, 'pause@example.com');

        $campaign = $this->createCampaign(['status' => 'paused', 'started_at' => now()]);
        $this->createSend($campaign, 100);

        (new ProcessCampaignSendsJob($this->companyA->id, $campaign->id))
            ->handle($this->app->make(CrmEmailService::class));

        $this->assertCount(0, $provider->calls);
        $this->assertSame(1, CrmCampaignSend::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('status', 'pending')->count());
        $this->assertSame('paused', $campaign->refresh()->status);
    }

    // ─── Commande planifiée ─────────────────────────────────────────────────

    public function test_command_auto_starts_due_scheduled_campaign_and_drains_sends(): void
    {
        $provider = $this->recordingProvider();
        $this->allowConsentFor([100]);

        $this->insertContact(100, 'due@example.com');

        $campaign = $this->createCampaign([
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(10),
        ]);

        $this->artisan('crm:process-campaign-sends')->assertExitCode(0);

        // Auto-start (audience filtrée par consentement) + drainage (queue
        // sync en tests) + auto-finish : la campagne est terminée et l'envoi
        // porte le contenu de la campagne.
        $campaign->refresh();
        $this->assertSame('finished', $campaign->status);
        $this->assertNotNull($campaign->started_at);
        $this->assertCount(1, $provider->calls);
        $this->assertSame('Offre de rentrée', $provider->calls[0]->subject);
        $this->assertSame(1, CrmCampaignSend::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('status', 'sent')->count());
    }

    public function test_command_ignores_scheduled_campaign_not_yet_due(): void
    {
        $this->recordingProvider();
        $this->allowConsentFor([100]);

        $campaign = $this->createCampaign([
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);

        $this->artisan('crm:process-campaign-sends')->assertExitCode(0);

        $this->assertSame('scheduled', $campaign->refresh()->status);
    }

    public function test_command_auto_finishes_running_campaign_without_pending_sends(): void
    {
        $this->recordingProvider();

        $campaign = $this->createCampaign(['status' => 'running', 'started_at' => now()]);
        $this->createSend($campaign, 100, 'sent');

        $this->artisan('crm:process-campaign-sends')->assertExitCode(0);

        $campaign->refresh();
        $this->assertSame('finished', $campaign->status);
        $this->assertNotNull($campaign->finished_at);
    }

    public function test_command_does_not_start_undue_or_foreign_state_campaigns(): void
    {
        $this->recordingProvider();
        $this->allowConsentFor([100]);

        $draft = $this->createCampaign(['status' => 'draft']);
        $paused = $this->createCampaign(['status' => 'paused', 'started_at' => now()]);

        $this->artisan('crm:process-campaign-sends')->assertExitCode(0);

        $this->assertSame('draft', $draft->refresh()->status);
        $this->assertSame('paused', $paused->refresh()->status);
    }
}

/**
 * Fournisseur email enregistreur (fixture #7751) — capture les messages pour
 * vérifier que le contenu de campagne est bien celui envoyé.
 */
final class CampaignSendRecordingProvider implements EmailProviderInterface
{
    /** @var list<EmailMessage> */
    public array $calls = [];

    public function send(EmailMessage $message): EmailDeliveryResult
    {
        $this->calls[] = $message;

        return EmailDeliveryResult::sent('campaign-recorder-'.count($this->calls));
    }

    public function providerName(): string
    {
        return 'campaign-recorder';
    }
}
