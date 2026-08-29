<?php

declare(strict_types=1);

namespace Tests\Feature\Seed;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Seed\PilotSeedGuard;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\CrmPilotSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-012 (#5870) — seeds pilotes : reproductibles, idempotents, nettoyables,
 * impossibles à diriger vers un tenant de production.
 *
 * Les tables crm_* sont créées à la volée (miroirs #5708/#5709, même pattern
 * que CrmPilotSeederTest) pour rester autosuffisant.
 */
class PilotSeedCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCrmTables();
    }

    public function test_pilot_seed_creates_pilots_and_is_idempotent(): void
    {
        $this->artisan('pilot:seed', ['vertical' => 'crm'])->assertExitCode(0);

        foreach (['crm-pilot-alpha', 'crm-pilot-beta'] as $slug) {
            $company = Company::query()->where('slug', $slug)->first();

            if (! $company instanceof Company) {
                $this->fail("Pilote [{$slug}] absent après seed");
            }

            $this->assertSame(2, Employee::query()->where('company_id', $company->id)->count());
        }

        // Réentrance : second run sans erreur, aucun doublon.
        $this->artisan('pilot:seed', ['vertical' => 'crm'])->assertExitCode(0);
        $this->assertSame(1, Company::query()->where('slug', 'crm-pilot-alpha')->count());
    }

    public function test_pilot_cleanup_removes_pilot_tenants_and_is_idempotent(): void
    {
        $this->seed(CrmPilotSeeder::class);

        $this->artisan('pilot:cleanup', ['vertical' => 'crm'])->assertExitCode(0);

        $this->assertNull(Company::query()->where('slug', 'crm-pilot-alpha')->first());
        $this->assertNull(Company::query()->where('slug', 'crm-pilot-beta')->first());
        $this->assertSame(0, DB::table('crm_accounts')->count());

        // Second run : no-op.
        $this->artisan('pilot:cleanup', ['vertical' => 'crm'])->assertExitCode(0);
    }

    public function test_pilot_cleanup_targets_only_allowlisted_slugs(): void
    {
        Company::factory()->create(['slug' => 'acme-real-client', 'name' => 'Acme Real']);

        $this->artisan('pilot:cleanup', ['vertical' => 'crm', '--tenant' => 'acme-real-client'])->assertFailed();

        $this->assertNotNull(Company::query()->where('slug', 'acme-real-client')->first());
    }

    public function test_pilot_seed_unknown_vertical_fails_cleanly(): void
    {
        $this->artisan('pilot:seed', ['vertical' => 'nope'])->assertFailed();
        $this->artisan('pilot:cleanup', ['vertical' => 'nope'])->assertFailed();
    }

    public function test_guard_refuses_production_without_force(): void
    {
        $guard = app(PilotSeedGuard::class);

        try {
            $guard->assertEnvironment('production');
            $this->fail('Devrait refuser production sans --force');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        // Ne lève pas :
        $guard->assertEnvironment('production', true);
        $guard->assertEnvironment('testing');
    }

    public function test_guard_refuses_non_allowlisted_slug(): void
    {
        $guard = app(PilotSeedGuard::class);

        $this->expectException(RuntimeException::class);
        $guard->assertPilotSlug('acme-real-client');
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

        if (! Schema::hasTable('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->timestampTz('due_at')->nullable();
                $table->unsignedBigInteger('assignee_id')->nullable();
                $table->boolean('done')->default(false);
                $table->timestamps();
            });
        }
    }
}
