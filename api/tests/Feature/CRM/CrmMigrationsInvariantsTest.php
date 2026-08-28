<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5709 — invariants portés par le schéma CRM client V0.
 *
 * Vérifie que les contraintes déclarées dans les migrations sont RÉELLEMENT
 * appliquées en base : CHECK (statuts/priorités/positions/montants),
 * unicité tenant-scopée et surtout rejet des relations cross-tenant via les
 * FK composites (pipeline_id, company_id) / (stage_id, company_id).
 *
 * Le CRM commercial Leopardo (Platform/Marketing) n'est pas concerné : ces
 * tables appartiennent aux espaces client (tenant).
 */
class CrmMigrationsInvariantsTest extends TestCase
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

    public function test_tables_exist_with_company_id(): void
    {
        foreach (['crm_pipelines', 'crm_pipeline_stages', 'crm_leads', 'crm_opportunities'] as $table) {
            $this->assertTrue(DB::getSchemaBuilder()->hasTable($table), "Table {$table} manquante.");
            $this->assertTrue(DB::getSchemaBuilder()->hasColumn($table, 'company_id'), "company_id manquant sur {$table}.");
        }
    }

    // ---------------------------------------------------------------------
    // CHECK constraints — valeurs inconnues rejetées
    // ---------------------------------------------------------------------

    public function test_lead_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        DB::table('crm_leads')->insert([
            'company_id' => $this->companyA->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'status' => 'inconnu',
        ]);
    }

    public function test_lead_rejects_unknown_priority(): void
    {
        $this->expectException(QueryException::class);

        DB::table('crm_leads')->insert([
            'company_id' => $this->companyA->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'priority' => 'urgent',
        ]);
    }

    public function test_stage_rejects_negative_position(): void
    {
        $pipelineId = $this->createPipeline($this->companyA->id);

        $this->expectException(QueryException::class);

        DB::table('crm_pipeline_stages')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineId,
            'name' => 'Négatif',
            'position' => -1,
        ]);
    }

    public function test_stage_rejects_won_and_lost_together(): void
    {
        $pipelineId = $this->createPipeline($this->companyA->id);

        $this->expectException(QueryException::class);

        DB::table('crm_pipeline_stages')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineId,
            'name' => 'Les deux',
            'position' => 1,
            'is_won' => true,
            'is_lost' => true,
        ]);
    }

    public function test_opportunity_rejects_negative_amount(): void
    {
        [$pipelineId, $stageId] = $this->createPipelineWithStage($this->companyA->id);

        $this->expectException(QueryException::class);

        DB::table('crm_opportunities')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineId,
            'stage_id' => $stageId,
            'name' => 'Affaire négative',
            'amount' => -100.00,
        ]);
    }

    // ---------------------------------------------------------------------
    // Unicité tenant-scopée
    // ---------------------------------------------------------------------

    public function test_pipeline_name_unique_per_company(): void
    {
        $this->createPipeline($this->companyA->id, 'Pipeline A');

        $this->expectException(QueryException::class);
        $this->createPipeline($this->companyA->id, 'Pipeline A');
    }

    public function test_same_pipeline_name_allowed_across_companies(): void
    {
        $this->createPipeline($this->companyA->id, 'Pipeline Ventes');
        $this->createPipeline($this->companyB->id, 'Pipeline Ventes'); // aucun doublon cross-tenant

        $this->assertSame(2, DB::table('crm_pipelines')->count());
    }

    public function test_stage_position_unique_per_pipeline(): void
    {
        $pipelineA = $this->createPipeline($this->companyA->id);
        $pipelineB = $this->createPipeline($this->companyB->id);

        $this->createStage($pipelineA, $this->companyA->id, 0);

        // Même position dans un AUTRE pipeline du même tenant : autorisé.
        DB::table('crm_pipeline_stages')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineB,
            'name' => 'Premier aussi',
            'position' => 0,
        ]);

        // Même position dans le MÊME pipeline : refusé.
        $this->expectException(QueryException::class);
        $this->createStage($pipelineA, $this->companyA->id, 0);
    }

    // ---------------------------------------------------------------------
    // Relations cross-tenant impossibles (FK composites)
    // ---------------------------------------------------------------------

    public function test_stage_cannot_reference_pipeline_of_another_company(): void
    {
        $pipelineOfB = $this->createPipeline($this->companyB->id);

        $this->expectException(QueryException::class);

        DB::table('crm_pipeline_stages')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineOfB,
            'name' => 'Fuite',
            'position' => 0,
        ]);
    }

    public function test_opportunity_cannot_reference_pipeline_of_another_company(): void
    {
        $pipelineOfB = $this->createPipeline($this->companyB->id);
        $stageOfB = $this->createStage($pipelineOfB, $this->companyB->id, 0);

        $this->expectException(QueryException::class);

        DB::table('crm_opportunities')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineOfB,
            'stage_id' => $stageOfB,
            'name' => 'Fuite pipeline',
        ]);
    }

    public function test_opportunity_cannot_reference_stage_of_another_company(): void
    {
        $pipelineA = $this->createPipeline($this->companyA->id);
        $stageOfB = $this->createStage($this->createPipeline($this->companyB->id), $this->companyB->id, 0);

        $this->expectException(QueryException::class);

        DB::table('crm_opportunities')->insert([
            'company_id' => $this->companyA->id,
            'pipeline_id' => $pipelineA,
            'stage_id' => $stageOfB,
            'name' => 'Fuite stage',
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function createPipeline(string $companyId, string $name = 'Pipeline'): int
    {
        return DB::table('crm_pipelines')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
        ]);
    }

    private function createStage(int $pipelineId, string $companyId, int $position): int
    {
        return DB::table('crm_pipeline_stages')->insertGetId([
            'company_id' => $companyId,
            'pipeline_id' => $pipelineId,
            'name' => 'Stage '.$position,
            'position' => $position,
        ]);
    }

    /** @return array{0: int, 1: int} */
    private function createPipelineWithStage(string $companyId): array
    {
        $pipelineId = $this->createPipeline($companyId);
        $stageId = $this->createStage($pipelineId, $companyId, 0);

        return [$pipelineId, $stageId];
    }
}
