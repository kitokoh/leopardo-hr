<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\CrmBenchmarkSeeder;
use Database\Seeders\CrmPilotSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5743 (CRM-PRE) — Seeds pilote CRM : déterministes, réinitialisables,
 * compatibles CI, sans données réelles ni secrets.
 *
 * Les tables crm_* sont créées à la volée (miroirs #5708/#5709, en cours)
 * pour que cette PR reste autosuffisante.
 */
class CrmPilotSeederTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCrmTables();
    }

    protected function tearDown(): void
    {
        $this->dropCrmTables();
        parent::tearDown();
    }

    public function test_pilot_seeder_creates_two_deterministic_tenants_with_full_demo_path(): void
    {
        $this->seed(CrmPilotSeeder::class);

        foreach (['crm-pilot-alpha', 'crm-pilot-beta'] as $slug) {
            $company = Company::query()->where('slug', $slug)->firstOrFail();

            self::assertSame(2, Employee::query()
                ->where('company_id', $company->id)
                ->count(), "{$slug} : un principal + un rh");

            // Parcours complet : account → contacts → leads → pipeline →
            // opportunities → tasks.
            self::assertSame(1, $this->countCrm('crm_accounts', $company), "{$slug} : 1 account");
            self::assertSame(2, $this->countCrm('crm_contacts', $company), "{$slug} : 2 contacts");
            self::assertSame(1, $this->countCrm('crm_contacts', $company, ['is_primary' => true]), "{$slug} : 1 contact primaire");
            self::assertSame(2, $this->countCrm('crm_leads', $company), "{$slug} : 2 leads");
            self::assertSame(1, $this->countCrm('crm_pipelines', $company), "{$slug} : 1 pipeline");
            self::assertSame(2, $this->countCrm('crm_opportunities', $company), "{$slug} : 2 opportunities");
            self::assertSame(1, $this->countCrm('crm_opportunities', $company, ['stage' => 'won']), "{$slug} : 1 opportunité gagnée");
            self::assertSame(2, $this->countCrm('crm_tasks', $company), "{$slug} : 2 tasks");
        }
    }

    public function test_pilot_seeder_is_reentrant(): void
    {
        $this->seed(CrmPilotSeeder::class);
        $this->seed(CrmPilotSeeder::class); // re-run → skip

        self::assertSame(2, Company::query()->where('slug', 'like', 'crm-pilot-%')->count());

        $company = Company::query()->where('slug', 'crm-pilot-alpha')->firstOrFail();
        self::assertSame(1, $this->countCrm('crm_accounts', $company), 'pas de doublon après re-run');
    }

    public function test_benchmark_seeder_is_separate_from_functional_fixtures(): void
    {
        $this->seed(CrmPilotSeeder::class);
        $this->seed(CrmBenchmarkSeeder::class);

        // L'entreprise benchmark est distincte des tenants pilotes.
        $benchmark = Company::query()->where('slug', 'benchmark-crm-dz')->firstOrFail();
        self::assertSame(500, $this->countCrm('crm_accounts', $benchmark));
        self::assertSame(1500, $this->countCrm('crm_contacts', $benchmark));
        self::assertSame(800, $this->countCrm('crm_leads', $benchmark));
        self::assertSame(300, $this->countCrm('crm_opportunities', $benchmark));
        self::assertSame(600, $this->countCrm('crm_tasks', $benchmark));

        // Les fixtures pilotes ne sont pas polluées.
        $alpha = Company::query()->where('slug', 'crm-pilot-alpha')->firstOrFail();
        self::assertSame(1, $this->countCrm('crm_accounts', $alpha));
    }

    public function test_seeder_contains_no_real_secrets(): void
    {
        $seeder = (string) file_get_contents(base_path('database/seeders/CrmPilotSeeder.php'));

        self::assertStringNotContainsString('ghp_', $seeder);
        self::assertStringNotContainsString('BEGIN PRIVATE KEY', $seeder);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $where
     */
    private function countCrm(string $table, Company $company, array $where = []): int
    {
        $query = DB::table($table)->where('company_id', $company->id);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return $query->count();
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

    private function dropCrmTables(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_accounts');
    }
}
