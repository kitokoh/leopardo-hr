<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmExportJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CRM V1 — Exports asynchrones et read models (issue #5729).
 *
 * Jobs asynchrones avec progression, colonnes allowlistées, accès expirant,
 * cleanup et audit ; read models recalculables schéma-gardés ; isolation
 * tenant systématique (404).
 */
class CrmExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);

        // Table source V0 minimale (miroir de la migration #5708) pour tester
        // le flux d'export de bout en bout avant merge du socle V0.
        if (! Schema::hasTable('crm_accounts')) {
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('name', 255);
                $table->string('status', 30)->default('active');
                $table->string('owner_id', 64)->nullable();
                $table->string('industry', 120)->nullable();
                $table->string('website', 255)->nullable();
                $table->timestamps();
            });
        }

        \Illuminate\Support\Facades\DB::table('crm_accounts')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $this->companyA->id,
            'name' => 'Acme SARL',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    public function test_export_job_flow_completes_and_downloads_csv(): void
    {
        Storage::fake('private');
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/exports', [
            'entity' => 'accounts',
            'columns' => ['name', 'status'],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'queued');
        $exportId = $response->json('data.id');

        // Queue sync (APP_ENV=testing) → le job s'exécute dans la requête.
        $this->getJson('/api/v1/crm/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', 100);

        $download = $this->get('/api/v1/crm/exports/'.$exportId.'/download');
        $download->assertOk();
        $this->assertStringContainsString('Acme SARL', $download->streamedContent());
        $this->assertStringContainsString('name,status', $download->streamedContent());
    }

    public function test_non_allowlisted_column_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/exports', [
            'entity' => 'accounts',
            'columns' => ['name', 'password_hash'],
        ])->assertStatus(422);
    }

    public function test_unknown_entity_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/exports', [
            'entity' => 'customers',
        ])->assertStatus(422);
    }

    public function test_unavailable_entity_returns_422(): void
    {
        // crm_contacts n'est PAS créée dans ce test → socle V0 absent.
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/exports', [
            'entity' => 'contacts',
        ])->assertStatus(422)->assertJsonPath('error', 'CRM_EXPORT_ENTITY_UNAVAILABLE');
    }

    public function test_expired_export_cannot_be_downloaded(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $job = CrmExportJob::query()->create([
            'company_id' => $this->companyA->id,
            'user_id' => null,
            'entity' => 'accounts',
            'format' => 'csv',
            'status' => 'completed',
            'progress' => 100,
            'file_path' => 'crm-exports/x.csv',
            'file_name' => 'x.csv',
            'expires_at' => now()->subHour(),
        ]);

        $this->get('/api/v1/crm/exports/'.$job->id.'/download')
            ->assertStatus(410)
            ->assertJsonPath('error', 'CRM_EXPORT_EXPIRED');

        $this->assertDatabaseHas('crm_export_jobs', [
            'id' => $job->id,
            'status' => 'expired',
        ]);
    }

    public function test_not_ready_export_returns_409(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $job = CrmExportJob::query()->create([
            'company_id' => $this->companyA->id,
            'entity' => 'accounts',
            'format' => 'csv',
            'status' => 'queued',
            'progress' => 0,
        ]);

        $this->get('/api/v1/crm/exports/'.$job->id.'/download')
            ->assertStatus(409)
            ->assertJsonPath('error', 'CRM_EXPORT_NOT_READY');
    }

    public function test_other_tenant_export_is_not_visible(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $job = CrmExportJob::query()->create([
            'company_id' => $this->companyB->id,
            'entity' => 'accounts',
            'format' => 'csv',
            'status' => 'completed',
            'progress' => 100,
        ]);

        $this->getJson('/api/v1/crm/exports/'.$job->id)->assertStatus(404);
        $this->get('/api/v1/crm/exports/'.$job->id.'/download')->assertStatus(404);
    }

    public function test_read_models_return_safe_defaults_without_v0_tables(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // crm_leads/crm_opportunities absents → agrégats vides, pas d'erreur.
        $this->getJson('/api/v1/crm/read-models')
            ->assertOk()
            ->assertJsonPath('data.accounts.active', 1)
            ->assertJsonPath('data.leads', [])
            ->assertJsonPath('data.opportunities', [])
            ->assertJsonPath('data.data_quality.overall', 100);
    }

    public function test_exports_index_lists_tenant_jobs(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        CrmExportJob::query()->create([
            'company_id' => $this->companyA->id,
            'entity' => 'accounts',
            'format' => 'csv',
            'status' => 'completed',
            'progress' => 100,
        ]);

        $this->getJson('/api/v1/crm/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_read_models_compute_pipeline_totals_when_v0_tables_exist(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        if (! Schema::hasTable('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('name', 160);
                $table->json('stages')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('pipeline_id')->nullable()->index();
                $table->string('stage', 40)->default('new');
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestamp('expected_close_at')->nullable();
                $table->timestamps();
            });
        }

        $pipelineId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('crm_pipelines')->insert([
            'id' => $pipelineId,
            'company_id' => $this->companyA->id,
            'name' => 'Ventes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('crm_opportunities')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineId,
            'stage' => 'negotiation',
            'amount' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/crm/read-models')
            ->assertOk()
            ->assertJsonPath('data.opportunities.negotiation', 1)
            ->assertJsonPath('data.pipeline.total', 1)
            ->assertJsonPath('data.pipeline.weighted', 5000);
    }
}
