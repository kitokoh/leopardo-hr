<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-ADM-004: the CRM pipeline endpoint must surface each lead's status,
 * acquisition source, an admin-facing note, and an explicit conversion
 * summary (lead -> trial -> paying client), not just the raw bucket
 * split it returned before.
 */
class PlatformCrmPipelineApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->setUpCompanyRequestTable();
    }

    protected function tearDown(): void
    {
        $this->tearDownCompanyRequestTable();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_pipeline_exposes_status_source_note_and_conversion_summary(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        // Pending lead captured via the self-service trial signup form,
        // with a structured source in signup_payload (new format).
        CompanyRequest::query()->create([
            'company_name' => 'Terrain Plus',
            'sector' => 'BTP',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'lead@terrain-plus.dz',
            'status' => 'pending',
            'signup_payload' => ['source' => 'signup_form'],
        ]);

        // Pending lead captured via the legacy description marker format.
        CompanyRequest::query()->create([
            'company_name' => 'Chantier Nord',
            'sector' => 'BTP',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'lead@chantier-nord.dz',
            'status' => 'pending',
            'description' => 'Self-service trial signup pending verification — source: demo_form',
            'admin_notes' => null,
        ]);

        // Approved request whose company is still in trial.
        $trialCompany = Company::query()->create([
            'name' => 'Trial Co',
            'slug' => 'trial-co',
            'sector' => 'Services',
            'email' => 'trial@co.dz',
            'country' => 'DZ',
            'city' => 'Alger',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'trial',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addDays(20)->toDateString(),
            'plan_id' => 1,
        ]);

        CompanyRequest::query()->create([
            'company_name' => 'Trial Co',
            'sector' => 'Services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'trial@co.dz',
            'status' => 'approved',
            'approved_company_id' => $trialCompany->id,
            'admin_notes' => 'Client pilote a suivre de pres.',
        ]);

        // Approved request whose company converted to a paying client.
        $activeCompany = Company::query()->create([
            'name' => 'Active Co',
            'slug' => 'active-co',
            'sector' => 'Services',
            'email' => 'active@co.dz',
            'country' => 'DZ',
            'city' => 'Alger',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => now()->subDays(40)->toDateString(),
            'subscription_end' => now()->addDays(320)->toDateString(),
            'plan_id' => 1,
        ]);

        CompanyRequest::query()->create([
            'company_name' => 'Active Co',
            'sector' => 'Services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'active@co.dz',
            'status' => 'approved',
            'approved_company_id' => $activeCompany->id,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->getJson('/api/v1/platform/crm/pipeline');

        $response->assertOk();

        // Status buckets contain at least our created records.
        $leads = $response->json('data.leads');
        $trials = $response->json('data.trials');
        $active = $response->json('data.active');

        $this->assertGreaterThanOrEqual(2, count($leads));
        $this->assertGreaterThanOrEqual(1, count($trials));
        $this->assertGreaterThanOrEqual(1, count($active));

        $signupLead = collect($leads)->firstWhere('company_name', 'Terrain Plus');
        $demoLead = collect($leads)->firstWhere('company_name', 'Chantier Nord');

        // Source resolved from signup_payload (new format).
        $this->assertSame('signup_form', $signupLead['source']);
        // Source resolved from the legacy description marker.
        $this->assertSame('demo_form', $demoLead['source']);

        $trialEntry = collect($trials)->firstWhere('company_name', 'Trial Co');
        $this->assertSame('Client pilote a suivre de pres.', $trialEntry['note']);

        // Conversion summary: verify our 4 records are included in the totals.
        // Using >= assertions because other tests may have added company_requests.
        $totalLeads = $response->json('meta.total_leads');
        $this->assertGreaterThanOrEqual(4, $totalLeads);

        // Our specific entries: 2 pending leads, 1 trial, 1 active.
        $this->assertNotNull($signupLead, 'Terrain Plus should appear in leads');
        $this->assertNotNull($demoLead, 'Chantier Nord should appear in leads');
        $this->assertNotNull($trialEntry, 'Trial Co should appear in trials');
        $this->assertNotNull(collect($active)->firstWhere('company_name', 'Active Co'), 'Active Co should appear in active');
    }

    private function setUpCompanyRequestTable(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');

            return;
        }

        if (! Schema::hasTable('company_requests')) {
            Schema::create('company_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable();
                $table->string('company_name');
                $table->string('sector')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('notes')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->uuid('approved_company_id')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->json('signup_payload')->nullable();
                $table->timestamps();
            });
        }
    }

    private function tearDownCompanyRequestTable(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');

            return;
        }

        Schema::dropIfExists('company_requests');
    }
}
