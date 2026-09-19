<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Application\Services\CampaignService;
use App\Modules\CRM\Application\Services\CrmEmailService;
use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Events\CampaignStarted;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use App\Modules\CRM\Infrastructure\Jobs\ProcessCampaignSendsJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7751 — envoi effectif des campagnes email.
 *
 * Couvre : contenu de campagne requis au start (canal email), listener
 * CampaignStarted → job, drainage des sends pending (statuts sent/failed),
 * respect de pause, auto-start des campagnes planifiées dues, auto-finish
 * quand plus rien n'est pending, commande planifiée.
 */
class CrmCampaignSendProcessingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->ensureContactsTable();
        $this->ensureCampaignSendsTable();

        // Consentement accordé pour tous les contacts de test.
        $this->app->instance(CampaignConsentCheckerInterface::class, new class implements CampaignConsentCheckerInterface
        {
            public function allows(int $contactId, string $channel): bool
            {
                return true;
            }
        });
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
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

    private function ensureCampaignSendsTable(): void
    {
        if (Schema::hasTable('crm_campaign_sends')) {
            return;
        }

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

    private function insertContact(int $id, ?string $email): void
    {
        DB::table('crm_contacts')->insert([
            'id' => $id,
            'company_id' => $this->company->id,
            'first_name' => 'Contact',
            'last_name' => (string) $id,
            'email' => $email,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CrmCampaign
    {
        app()->instance('current_company', $this->company);

        /** @var CrmCampaign $campaign */
        $campaign = CrmCampaign::query()->create(array_merge([
            'name' => 'Campagne test',
            'channel' => 'email',
            'status' => 'draft',
            'subject' => 'Sujet de test',
            'body' => 'Corps du message de test.',
        ], $attributes));

        return $campaign;
    }

    private function runJob(CrmCampaign $campaign): void
    {
        app()->instance('current_company', $this->company);

        $job = new ProcessCampaignSendsJob((string) $campaign->company_id, $campaign->id);
        $job->handle(app(CrmEmailService::class), app(CampaignService::class));
    }

    public function test_email_campaign_without_subject_cannot_start(): void
    {
        $this->insertContact(1, 'contact-1@example.com');
        $campaign = $this->campaign(['subject' => null, 'body' => null, 'audience_snapshot' => [1]]);

        $this->expectException(ValidationException::class);

        app(CampaignService::class)->start($campaign, null);
    }

    public function test_campaign_started_event_dispatches_processing_job(): void
    {
        Queue::fake();

        CampaignStarted::dispatch((string) $this->company->id, 42, 'email', 1);

        Queue::assertPushed(ProcessCampaignSendsJob::class, function (ProcessCampaignSendsJob $job): bool {
            return $job->campaignId === 42 && $job->companyId === (string) $this->company->id;
        });
    }

    public function test_campaign_started_event_for_sms_is_ignored(): void
    {
        Queue::fake();

        CampaignStarted::dispatch((string) $this->company->id, 43, 'sms', 1);

        Queue::assertNotPushed(ProcessCampaignSendsJob::class);
    }

    public function test_job_drains_pending_sends_and_finishes_campaign(): void
    {
        $this->insertContact(1, 'contact-1@example.com');
        $this->insertContact(2, null); // sans email → failed

        $campaign = $this->campaign(['status' => 'running', 'started_at' => now()]);

        foreach ([1, 2] as $contactId) {
            CrmCampaignSend::query()->create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'channel' => 'email',
                'status' => 'pending',
            ]);
        }

        $this->runJob($campaign);

        $statuses = CrmCampaignSend::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('contact_id')
            ->pluck('status')
            ->all();

        $this->assertSame(['sent', 'failed'], $statuses);

        $campaign->refresh();
        $this->assertSame('finished', $campaign->status);
        $this->assertNotNull($campaign->finished_at);
    }

    public function test_job_uses_campaign_subject_and_body(): void
    {
        $this->insertContact(1, 'contact-1@example.com');

        $campaign = $this->campaign([
            'status' => 'running',
            'started_at' => now(),
            'subject' => 'Promo rentrée',
            'body' => 'Contenu personnalisé de la campagne.',
        ]);

        $send = CrmCampaignSend::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => 1,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $this->runJob($campaign);

        $send->refresh();
        $this->assertSame('sent', $send->status);

        // Le message envoyé porte le sujet de la campagne (audité).
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'email.sent',
        ]);
        $audit = DB::table('audit_logs')
            ->where('company_id', $this->company->id)
            ->where('action', 'email.sent')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertIsString($audit->new_values);
        $this->assertStringContainsString('Promo rentrée', $audit->new_values);
    }

    public function test_job_does_nothing_when_campaign_is_paused(): void
    {
        $this->insertContact(1, 'contact-1@example.com');

        $campaign = $this->campaign(['status' => 'paused']);

        CrmCampaignSend::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => 1,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $this->runJob($campaign);

        $this->assertSame(
            'pending',
            CrmCampaignSend::query()->where('campaign_id', $campaign->id)->value('status'),
        );
        $this->assertSame('paused', $campaign->refresh()->status);
    }

    public function test_job_auto_starts_scheduled_campaign_due(): void
    {
        $this->insertContact(1, 'contact-1@example.com');

        $campaign = $this->campaign([
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(10),
            'audience_snapshot' => [1],
        ]);

        $this->runJob($campaign);

        $campaign->refresh();
        $this->assertSame('finished', $campaign->status);
        $this->assertSame(
            'sent',
            CrmCampaignSend::query()->where('campaign_id', $campaign->id)->value('status'),
        );
    }

    public function test_command_dispatches_jobs_for_due_and_running_campaigns(): void
    {
        Queue::fake();

        $this->insertContact(1, 'contact-1@example.com');

        $scheduled = $this->campaign([
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'audience_snapshot' => [1],
        ]);

        $running = $this->campaign(['status' => 'running', 'started_at' => now()]);
        CrmCampaignSend::query()->create([
            'campaign_id' => $running->id,
            'contact_id' => 1,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $this->artisan('crm:process-campaign-sends')->assertSuccessful();

        Queue::assertPushed(ProcessCampaignSendsJob::class, 2);
        Queue::assertPushed(ProcessCampaignSendsJob::class, fn (ProcessCampaignSendsJob $job): bool => $job->campaignId === $scheduled->id);
        Queue::assertPushed(ProcessCampaignSendsJob::class, fn (ProcessCampaignSendsJob $job): bool => $job->campaignId === $running->id);
    }
}
