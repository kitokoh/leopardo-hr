<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Infrastructure\Jobs\CrmImportCommitJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5714 — Cycle de vie complet de l'import CSV CRM.
 *
 * Tests écrits avant l'implémentation (DoD) :
 *  - preview sans écriture dans les tables cibles ;
 *  - PII masquée dans l'aperçu ;
 *  - commit explicite idempotent + audit ;
 *  - annulation avant commit ;
 *  - 404 sûr cross-tenant ;
 *  - 403 pour les rôles non autorisés.
 *
 * Les tables cibles (crm_accounts/crm_contacts/crm_leads) arrivent avec
 * #5708/#5709 : elles sont créées ici à la volée pour que cette PR reste
 * autosuffisante et passe la CI avant le merge des fondations.
 */
class CrmImportFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCrmTargetTables();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    protected function tearDown(): void
    {
        $this->dropCrmTargetTables();
        parent::tearDown();
    }

    public function test_preview_creates_session_without_writing_targets(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/crm/imports', [
            'entity_type' => 'accounts',
            'file' => $this->csv('accounts', "name,email\nAcme,acme@x.fr\nGlobex,globex@x.fr\n"),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'previewed')
            ->assertJsonPath('data.valid_rows', 2)
            ->assertJsonPath('data.error_rows', 0);

        self::assertSame(0, CrmAccount::query()->count(), 'preview ne doit rien écrire dans les tables cibles');
        self::assertSame(1, CrmImport::query()->count());
    }

    public function test_preview_masks_pii_in_sample(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/crm/imports', [
            'entity_type' => 'contacts',
            'file' => $this->csv('contacts', "first_name,last_name,email,phone\nJean,Dupont,jean.dupont@example.com,0601020304\n"),
        ]);

        $response->assertStatus(201);

        $preview = $response->json('data.preview_data.0');
        self::assertSame('jea***', $preview['email']);
        self::assertSame('060***', $preview['phone']);
        self::assertStringNotContainsString('jean.dupont@example.com', (string) $response->getContent());
    }

    public function test_preview_reports_per_line_errors(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/crm/imports', [
            'entity_type' => 'accounts',
            'file' => $this->csv('accounts', "name,email\nAcme,acme@x.fr\n,finance@x.fr\n"),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.error_rows', 1)
            ->assertJsonPath('data.errors.0.line', 3);
    }

    public function test_rejects_unknown_entity_type(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/crm/imports', [
            'entity_type' => 'invoices',
            'file' => $this->csv('accounts', "name\nAcme\n"),
        ])->assertStatus(422);
    }

    public function test_rejects_invalid_file(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/crm/imports', [
            'entity_type' => 'accounts',
            'file' => UploadedFile::fake()->createWithContent('data.xlsx', 'name'),
        ])->assertStatus(422);
    }

    public function test_commit_persists_rows_and_audits(): void
    {
        Sanctum::actingAs($this->manager);

        $importId = $this->createPreviewedImport('accounts', "name,email,notes\nAcme,acme@example.com,BTP\nGlobex,globex@example.com,Finance\n");

        $this->postJson("/api/v1/crm/imports/{$importId}/commit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'committed');

        self::assertSame(2, CrmAccount::query()->count());
        self::assertSame('Acme', CrmAccount::query()->orderBy('id')->first()?->name);

        self::assertSame(1, AuditLog::query()
            ->where('action', 'crm.import.committed')
            ->count(), 'le commit doit être audité');
    }

    public function test_commit_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager);

        $importId = $this->createPreviewedImport('accounts', "name,email\nAcme,acme@x.fr\n");

        $this->postJson("/api/v1/crm/imports/{$importId}/commit")->assertStatus(200);

        // Second commit → 409 CRM_IMPORT_ALREADY_COMMITTED (claim atomique).
        $this->postJson("/api/v1/crm/imports/{$importId}/commit")
            ->assertStatus(409)
            ->assertJsonPath('error', 'CRM_IMPORT_ALREADY_COMMITTED');

        self::assertSame(1, CrmAccount::query()->count(), 'pas de doublon après double commit');
    }

    public function test_cancel_prevents_commit(): void
    {
        Sanctum::actingAs($this->manager);

        $importId = $this->createPreviewedImport('accounts', "name,email\nAcme,acme@x.fr\n");

        $this->postJson("/api/v1/crm/imports/{$importId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/v1/crm/imports/{$importId}/commit")
            ->assertStatus(409)
            ->assertJsonPath('error', 'CRM_IMPORT_ALREADY_CANCELLED');

        self::assertSame(0, CrmAccount::query()->count());
    }

    public function test_cross_tenant_access_returns_404(): void
    {
        Sanctum::actingAs($this->manager);

        $importId = $this->createPreviewedImport('accounts', "name,email\nAcme,acme@x.fr\n");

        // Autre tenant → 404 sûr (jamais 403/200).
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($otherManager);

        $this->getJson("/api/v1/crm/imports/{$importId}")->assertStatus(404);
        $this->postJson("/api/v1/crm/imports/{$importId}/commit")->assertStatus(404);
        $this->postJson("/api/v1/crm/imports/{$importId}/cancel")->assertStatus(404);
    }

    public function test_non_manager_role_cannot_create_import(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/crm/imports', [
            'entity_type' => 'accounts',
            'file' => $this->csv('accounts', "name\nAcme\n"),
        ])->assertStatus(403);
    }

    public function test_contact_import_resolves_account_and_primary(): void
    {
        Sanctum::actingAs($this->manager);

        CrmAccount::query()->create(['company_id' => $this->company->id, 'name' => 'Acme', 'status' => 'active']);

        $importId = $this->createPreviewedImport('contacts', "first_name,last_name,email,account_name,is_primary\nJean,Dupont,jean@acme.fr,Acme,yes\nMarie,Martin,marie@acme.fr,Acme,yes\n");

        $this->postJson("/api/v1/crm/imports/{$importId}/commit")->assertStatus(200);

        self::assertSame(2, CrmContact::query()->count());
        self::assertSame(
            1,
            CrmContact::query()->where('is_primary', true)->count(),
            'au plus un contact primaire par compte'
        );
        self::assertSame(
            2,
            CrmContact::query()->where('account_id', CrmAccount::query()->where('name', 'Acme')->firstOrFail()->id)->count(),
            'les deux contacts importés résolvent le compte Acme existant (pas de doublon de compte)'
        );
    }

    public function test_lead_import_defaults_source_and_status(): void
    {
        Sanctum::actingAs($this->manager);

        $importId = $this->createPreviewedImport('leads', "first_name,last_name,email\nSarah,Khan,sarah@example.com\n");

        $this->postJson("/api/v1/crm/imports/{$importId}/commit")->assertStatus(200);

        $lead = CrmLead::query()->firstOrFail();
        self::assertSame('import', $lead->source);
        self::assertSame('new', $lead->status);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function csv(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent("{$name}.csv", $content);
    }

    private function createPreviewedImport(string $entityType, string $content): int
    {
        $response = $this->postJson('/api/v1/crm/imports', [
            'entity_type' => $entityType,
            'file' => $this->csv($entityType, $content),
        ]);
        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    private function createCrmTargetTables(): void
    {
        if (! Schema::hasTable('crm_accounts')) {
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 255);
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->text('email')->nullable();
                $table->string('phone', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->text('email')->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('title', 100)->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('opt_in_email')->default(false);
                $table->boolean('opt_in_sms')->default(false);
                $table->boolean('opt_in_whatsapp')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->uuid('account_id')->nullable();
                $table->uuid('owner_id')->nullable();
                $table->string('first_name', 120)->nullable();
                $table->string('last_name', 120)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('title', 255)->nullable();
                $table->string('source', 20)->default('manual');
                $table->string('status', 20)->default('new');
                $table->unsignedSmallInteger('score')->default(0);
                $table->json('tags')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function dropCrmTargetTables(): void
    {
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_accounts');
    }
}
