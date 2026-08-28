<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Migrations CRM client (tenant) — Issue #5709 (CRM-V0-05).
 *
 * Verrouille la création des tables `crm_pipelines`, `crm_pipeline_stages`,
 * `crm_leads` et `crm_opportunities` :
 *   1. placement dans le schéma tenant (`shared_tenants`) — pattern F-17 ;
 *   2. contraintes CHECK documentées (statuts, source, probabilités) ;
 *   3. `company_id` uuid NON nullable sur chaque table (isolation tenant) ;
 *   4. idempotence des gardes (re-run direct de la migration) ;
 *   5. rollback complet (down).
 */
class CrmLeadsPipelinesMigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const MIGRATION = '2026_08_28_000001_5709_create_crm_leads_pipelines_opportunities';

    private const PIPELINE_UUID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private function tableSchema(string $table): ?string
    {
        $row = DB::selectOne(
            'SELECT t.table_schema
               FROM information_schema.tables t
              WHERE t.table_name = ?
              ORDER BY t.table_schema
              LIMIT 1',
            [$table]
        );

        return $row ? (string) $row->table_schema : null;
    }

    public function test_tables_are_created_in_tenant_schema(): void
    {
        foreach (['crm_pipelines', 'crm_pipeline_stages', 'crm_leads', 'crm_opportunities'] as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table), "table {$table} absente du schéma tenant");
        }
    }

    public function test_company_id_is_non_nullable_on_every_table(): void
    {
        $this->expectException(QueryException::class);

        DB::table('crm_leads')->insert([
            'first_name' => 'Sans',
            'last_name' => 'Tenant',
        ]);
    }

    public function test_lead_status_check_rejects_unknown_status(): void
    {
        $pipelineId = $this->insertPipeline();

        try {
            DB::table('crm_leads')->insert([
                'company_id' => self::PIPELINE_UUID,
                'first_name' => 'Alice',
                'last_name' => 'Durand',
                'status' => 'inexistant',
                'pipeline_id' => $pipelineId,
            ]);
            $this->fail('Le CHECK crm_leads_status_check aurait dû rejeter le statut inconnu.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('crm_leads_status_check', $exception->getMessage());
        }
    }

    public function test_lead_source_check_rejects_unknown_source(): void
    {
        try {
            DB::table('crm_leads')->insert([
                'company_id' => self::PIPELINE_UUID,
                'first_name' => 'Bob',
                'last_name' => 'Martin',
                'source' => 'meteorite',
            ]);
            $this->fail('Le CHECK crm_leads_source_check aurait dû rejeter la source inconnue.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('crm_leads_source_check', $exception->getMessage());
        }
    }

    public function test_opportunity_status_check_rejects_unknown_status(): void
    {
        try {
            DB::table('crm_opportunities')->insert([
                'company_id' => self::PIPELINE_UUID,
                'name' => 'Affaire X',
                'status' => 'half_open',
            ]);
            $this->fail('Le CHECK crm_opportunities_status_check aurait dû rejeter le statut inconnu.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('crm_opportunities_status_check', $exception->getMessage());
        }
    }

    public function test_stage_probability_check_bounds(): void
    {
        $pipelineId = $this->insertPipeline();

        try {
            DB::table('crm_pipeline_stages')->insert([
                'company_id' => self::PIPELINE_UUID,
                'pipeline_id' => $pipelineId,
                'name' => 'Stage improbable',
                'position' => 1,
                'probability' => 150,
            ]);
            $this->fail('Le CHECK crm_pipeline_stages_probability_check aurait dû rejeter 150.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('crm_pipeline_stages_probability_check', $exception->getMessage());
        }
    }

    public function test_valid_inserts_are_accepted(): void
    {
        $pipelineId = $this->insertPipeline();

        DB::table('crm_pipeline_stages')->insert([
            'company_id' => self::PIPELINE_UUID,
            'pipeline_id' => $pipelineId,
            'name' => 'Proposition',
            'position' => 0,
            'probability' => 30,
        ]);

        DB::table('crm_leads')->insert([
            'company_id' => self::PIPELINE_UUID,
            'first_name' => 'Alice',
            'last_name' => 'Durand',
            'email' => 'alice@example.com',
            'source' => 'website',
            'status' => 'new',
        ]);

        $this->assertSame(1, DB::table('crm_leads')->where('company_id', self::PIPELINE_UUID)->count());
    }

    public function test_migration_is_idempotent(): void
    {
        // Re-run direct : les gardes schemaTableExists doivent absorber le rejeu.
        $migration = require database_path('migrations/tenant/'.self::MIGRATION.'.php');
        $migration->up();

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('crm_leads'));
    }

    public function test_rollback_drops_all_tables(): void
    {
        $migration = require database_path('migrations/tenant/'.self::MIGRATION.'.php');
        $migration->down();

        foreach (['crm_pipelines', 'crm_pipeline_stages', 'crm_leads', 'crm_opportunities'] as $table) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($table), "table {$table} encore présente après down()");
        }
    }

    private function insertPipeline(): int
    {
        return (int) DB::table('crm_pipelines')->insertGetId([
            'company_id' => self::PIPELINE_UUID,
            'name' => 'Ventes directes',
            'is_default' => true,
        ]);
    }
}
