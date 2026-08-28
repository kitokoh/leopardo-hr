<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5717 — Conversion guidée lead → account + contact + opportunity.
 *
 * Tests écrits avant l'implémentation (DoD) : création transactionnelle,
 * réutilisation du compte, idempotence (409), 404 cross-tenant, 403 rôle,
 * étape whitelistée, audit. Les tables CRM (issues #5708/#5709, en cours)
 * sont créées à la volée pour que cette PR soit autosuffisante.
 */
class CrmLeadConversionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCrmTables();

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
        $this->dropCrmTables();
        parent::tearDown();
    }

    public function test_conversion_creates_account_contact_and_opportunity(): void
    {
        Sanctum::actingAs($this->manager);

        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'converted');

        self::assertSame(1, CrmAccount::query()->where('name', 'Globex')->count());
        self::assertSame(1, CrmContact::query()->where('email', 'sarah@example.com')->count());
        self::assertSame(1, CrmOpportunity::query()->count());

        $lead->refresh();
        self::assertSame('converted', $lead->status);
        self::assertNotNull($lead->converted_at);

        self::assertSame(1, AuditLog::query()->where('action', 'crm.lead.converted')->count());
    }

    public function test_conversion_reuses_existing_account_and_sets_primary_contact(): void
    {
        Sanctum::actingAs($this->manager);

        CrmAccount::query()->create(['company_id' => $this->company->id, 'name' => 'Globex', 'status' => 'active']);
        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert")->assertStatus(200);

        self::assertSame(1, CrmAccount::query()->where('name', 'Globex')->count(), 'compte réutilisé, pas dupliqué');
        $contact = CrmContact::query()->where('email', 'sarah@example.com')->firstOrFail();
        self::assertTrue($contact->is_primary, 'premier contact du compte = primaire');
    }

    public function test_conversion_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager);

        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert")->assertStatus(200);
        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert")
            ->assertStatus(409)
            ->assertJsonPath('error', 'CRM_LEAD_ALREADY_CONVERTED');

        self::assertSame(1, CrmAccount::query()->count());
        self::assertSame(1, CrmOpportunity::query()->count());
    }

    public function test_conversion_accepts_stage_amount_and_close_date(): void
    {
        Sanctum::actingAs($this->manager);

        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert", [
            'stage' => 'negotiation',
            'amount' => 12500.50,
            'currency' => 'DZD',
            'expected_close_date' => now()->addDays(14)->toDateString(),
        ])->assertStatus(200);

        $opportunity = CrmOpportunity::query()->firstOrFail();
        self::assertSame('negotiation', $opportunity->stage);
        self::assertSame('12500.50', (string) $opportunity->amount);
        self::assertSame('DZD', $opportunity->currency);
        self::assertNotNull($opportunity->expected_close_date);
    }

    public function test_conversion_rejects_unknown_stage(): void
    {
        Sanctum::actingAs($this->manager);

        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert", ['stage' => 'blackhole'])
            ->assertStatus(422);
    }

    public function test_cross_tenant_lead_returns_404(): void
    {
        Sanctum::actingAs($this->manager);

        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($otherManager);

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert")->assertStatus(404);
    }

    public function test_non_manager_role_cannot_convert(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $lead = $this->createLead('Sarah', 'Khan', 'sarah@example.com', 'Globex');

        $this->postJson("/api/v1/crm/leads/{$lead->id}/convert")->assertStatus(403);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createLead(string $first, string $last, string $email, ?string $companyName): CrmLead
    {
        /** @var CrmLead $lead */
        $lead = CrmLead::query()->create([
            'company_id' => $this->company->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'company_name' => $companyName,
            'source' => 'manual',
            'status' => 'new',
        ]);

        return $lead;
    }

    private function createCrmTables(): void
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

        if (! Schema::hasTable('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 120);
                $table->boolean('is_default')->default(false);
                $table->json('stages');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->uuid('pipeline_id')->nullable();
                $table->uuid('lead_id')->nullable();
                $table->uuid('owner_id')->nullable();
                $table->string('name', 255);
                $table->string('stage', 80)->default('prospecting');
                $table->decimal('amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->date('expected_close_date')->nullable();
                $table->string('status', 10)->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function dropCrmTables(): void
    {
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_accounts');
    }
}
