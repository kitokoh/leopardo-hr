<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Application\Services\CrmDashboardReadModel;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\Support\CreatesCrmSchema;
use Tests\TestCase;

/**
 * Issue #9764 — le dashboard pipeline doit refléter les opportunités réelles
 * même quand `stage_id` est NULL (le cas de TOUTES les opportunités créées par
 * `ConvertLeadAction`, qui n'écrit que la colonne `stage`).
 *
 * Avant ce correctif, `CrmDashboardReadModel::pipeline()` joignait en INTERNE
 * sur `s.id = o.stage_id` : aucune ligne, donc `open_count = 0` et
 * `open_value = 0` alors que le kanban affichait les opportunités.
 *
 * Ce test verrouille :
 *  1. une opportunité avec `stage` seul (stage_id NULL) est comptée ;
 *  2. une opportunité avec `stage_id` renseigné est comptée via l'étape ;
 *  3. les deux chemins donnent le MÊME total (cohérence dashboard ↔ kanban) ;
 *  4. `won`/`lost` sont dérivés correctement dans les deux cas ;
 *  5. une étape d'un AUTRE tenant ne qualifie jamais l'opportunité.
 */
class CrmDashboardStageFallbackTest extends TestCase
{
    use CreatesCrmSchema;
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCrmSchemaIfMissing();

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

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    /**
     * Opportunité « legacy » : seule la colonne `stage` est écrite, comme le
     * fait `ConvertLeadAction`.
     */
    private function opportunityWithStageOnly(string $companyId, string $stage, float $amount): int
    {
        return DB::table('crm_opportunities')->insertGetId([
            'company_id' => $companyId,
            'stage' => $stage,
            'stage_id' => null,
            'name' => 'Opp '.$stage,
            'amount' => $amount,
            'currency' => 'DZD',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_opportunity_with_stage_only_is_counted(): void
    {
        $this->opportunityWithStageOnly($this->companyA->id, 'negotiation', 119000);

        $data = app(CrmDashboardReadModel::class)->pipeline((string) $this->companyA->id);

        $this->assertSame(1, $data['totals']['open_count']);
        $this->assertSame(119000.0, (float) $data['totals']['open_value']);
    }

    public function test_stage_only_and_stage_id_paths_agree(): void
    {
        // Chemin A : `stage` seul (ConvertLeadAction).
        $this->opportunityWithStageOnly($this->companyA->id, 'proposal', 50000);

        // Chemin B : `stage_id` renseigné (génération riche).
        $pipelineId = DB::table('crm_pipelines')->insertGetId([
            'company_id' => $this->companyA->id,
            'name' => 'Pipeline Test',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('crm_pipeline_stages')->insertGetId([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineId,
            'name' => 'Proposition',
            'position' => 2,
            'is_won' => false,
            'is_lost' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_opportunities')->insert([
            'company_id' => $this->companyA->id,
            'stage' => 'proposal',
            'stage_id' => $stageId,
            'name' => 'Opp stage_id',
            'amount' => 25000,
            'currency' => 'DZD',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = app(CrmDashboardReadModel::class)->pipeline((string) $this->companyA->id);

        // Les deux opportunités sont comptées, quel que soit le chemin.
        $this->assertSame(2, $data['totals']['open_count']);
        $this->assertSame(75000.0, (float) $data['totals']['open_value']);
    }

    public function test_won_and_lost_are_derived_from_stage_column(): void
    {
        $this->opportunityWithStageOnly($this->companyA->id, 'won', 45000);
        $this->opportunityWithStageOnly($this->companyA->id, 'lost', 10000);
        $this->opportunityWithStageOnly($this->companyA->id, 'prospecting', 5000);

        $data = app(CrmDashboardReadModel::class)->pipeline((string) $this->companyA->id);

        $this->assertSame(1, $data['totals']['won_count']);
        $this->assertSame(1, $data['totals']['lost_count']);
        $this->assertSame(1, $data['totals']['open_count']);
        $this->assertSame(5000.0, (float) $data['totals']['open_value']);
    }

    public function test_stage_of_another_tenant_never_qualifies(): void
    {
        // Étape appartenant à B, référencée par une opportunité de A.
        $pipelineB = DB::table('crm_pipelines')->insertGetId([
            'company_id' => $this->companyB->id,
            'name' => 'Pipeline B',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageB = DB::table('crm_pipeline_stages')->insertGetId([
            'company_id' => $this->companyB->id,
            'pipeline_id' => $pipelineB,
            'name' => 'Étape B',
            'position' => 0,
            'is_won' => true,
            'is_lost' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_opportunities')->insert([
            'company_id' => $this->companyA->id,
            'stage' => 'prospecting',
            'stage_id' => $stageB,
            'name' => 'Opp cross-tenant',
            'amount' => 1000,
            'currency' => 'DZD',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = app(CrmDashboardReadModel::class)->pipeline((string) $this->companyA->id);

        // La jointure composite (id, company_id) ne matche pas : repli sur
        // `stage` = prospecting → ouverte, PAS gagnée.
        $this->assertSame(1, $data['totals']['open_count']);
        $this->assertSame(0, $data['totals']['won_count']);
    }

    public function test_dashboard_endpoint_matches_kanban_source(): void
    {
        $this->opportunityWithStageOnly($this->companyA->id, 'negotiation', 119000);

        $manager = $this->manager($this->companyA);
        \Laravel\Sanctum\Sanctum::actingAs($manager);

        $dashboard = $this->getJson('/api/v1/crm/dashboard/pipeline');
        $dashboard->assertOk();

        // Le kanban lit /crm/opportunities : les deux doivent voir la même
        // opportunité et le même montant.
        $kanban = $this->getJson('/api/v1/crm/opportunities');
        $kanban->assertOk();

        $this->assertSame(1, $dashboard->json('data.totals.open_count'));
        $this->assertSame(119000.0, (float) $dashboard->json('data.totals.open_value'));
        $this->assertCount(1, $kanban->json('data'));
        $this->assertSame(119000.0, (float) $kanban->json('data.0.amount'));
    }
}
