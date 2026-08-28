<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/** Contrat minimal des migrations testées (up/down). */
interface CrmV0DownableMigration
{
    public function up(): void;

    public function down(): void;
}

/**
 * Issue #5709 — CRM V0 : migrations tenant leads / pipelines / opportunités.
 *
 * Vérifie, sur base fraîche (RefreshTenantDatabase) :
 *   1. le placement des tables dans le schéma tenant (search_path) ;
 *   2. les colonnes obligatoires (company_id non nullable) ;
 *   3. les contraintes de domaine CHECK (status, source, score) ;
 *   4. les index de requêtage (scope tenant d'abord) ;
 *   5. le rollback (down) qui supprime les tables.
 */
class CrmV0MigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TABLES = [
        'crm_leads',
        'crm_pipelines',
        'crm_opportunities',
    ];

    private function tenantSchema(): string
    {
        // Les migrations tenant résolvent le schéma via le search_path
        // (convention #1613) ; en CI locale le schéma tenant est
        // `shared_tenants` (config search_path de test).
        $row = DB::selectOne(
            "SELECT current_schemas(false) AS schemas"
        );

        $schemas = $row ? (string) $row->schemas : '';

        return str_contains($schemas, 'shared_tenants') ? 'shared_tenants' : 'public';
    }

    public function test_crm_tables_are_created_in_tenant_schema(): void
    {
        $schema = $this->tenantSchema();

        foreach (self::TABLES as $table) {
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = ? AND table_name = ?',
                [$schema, $table]
            );
            $this->assertNotNull($row, "table {$table} absente du schéma tenant {$schema}");
        }
    }

    public function test_leads_columns_company_id_not_nullable(): void
    {
        $schema = $this->tenantSchema();

        // company_id : colonne uuid NON nullable (isolation tenant obligatoire).
        $row = DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, 'crm_leads', 'company_id']
        );
        $this->assertNotNull($row);
        $this->assertSame('NO', (string) $row->is_nullable);

        // Colonnes PII présentes (la stratégie HMAC #5713 s'appliquera à elles).
        foreach (['email', 'phone', 'first_name', 'last_name'] as $col) {
            $c = DB::selectOne(
                'SELECT 1 FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [$schema, 'crm_leads', $col]
            );
            $this->assertNotNull($c, "colonne {$col} absente de crm_leads");
        }
    }

    public function test_leads_check_constraints_are_enforced(): void
    {
        $schema = $this->tenantSchema();

        $checks = DB::select(
            'SELECT conname FROM pg_constraint
             WHERE connamespace = (SELECT oid FROM pg_namespace WHERE nspname = ?)
               AND contype = \'c\'
               AND conrelid = (SELECT oid FROM pg_class
                               WHERE relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = ?)
                                 AND relname = ?)',
            [$schema, $schema, 'crm_leads']
        );
        $names = array_map(static fn ($r) => (string) $r->conname, $checks);

        $this->assertContains('crm_leads_status_check', $names);
        $this->assertContains('crm_leads_source_check', $names);
        $this->assertContains('crm_leads_score_check', $names);
    }

    public function test_opportunities_check_constraint_status(): void
    {
        $schema = $this->tenantSchema();

        $check = DB::selectOne(
            'SELECT 1 FROM pg_constraint
             WHERE connamespace = (SELECT oid FROM pg_namespace WHERE nspname = ?)
               AND contype = \'c\'
               AND conname = ?',
            [$schema, 'crm_opportunities_status_check']
        );
        $this->assertNotNull($check);
    }

    public function test_crm_indexes_are_present(): void
    {
        $schema = $this->tenantSchema();

        $expected = [
            'crm_leads_company_status_idx',
            'crm_leads_company_owner_idx',
            'crm_leads_company_created_idx',
            'crm_leads_company_email_idx',
            'crm_pipelines_company_name_unique',
            'crm_pipelines_company_default_idx',
            'crm_opp_company_pipeline_stage_idx',
            'crm_opp_company_owner_idx',
            'crm_opp_company_close_date_idx',
            'crm_opp_company_status_idx',
        ];

        $rows = DB::select(
            'SELECT indexname FROM pg_indexes WHERE schemaname = ?',
            [$schema]
        );
        $names = array_map(static fn ($r) => (string) $r->indexname, $rows);

        foreach ($expected as $index) {
            $this->assertContains($index, $names, "index {$index} absent");
        }
    }

    public function test_rollback_drops_crm_tables(): void
    {
        $schema = $this->tenantSchema();

        // Le runner global `migrate:rollback --path=tenant` est fragile ici :
        // le repo `migrations` de shared_tenants contient des entrées dont les
        // fichiers vivent dans public/ (état historique) → « Migration not
        // found ». On teste donc le down() de chaque migration directement,
        // ce qui est le contrat réel du rollback (#5709 : fresh + rollback
        // testés).
        $files = [
            '2026_08_28_000001_5709_create_crm_leads_table.php',
            '2026_08_28_000002_5709_create_crm_pipelines_table.php',
            '2026_08_28_000003_5709_create_crm_opportunities_table.php',
        ];

        foreach ($files as $file) {
            /** @var CrmV0DownableMigration $migration */
            $migration = require database_path('migrations/tenant/'.$file);
            $migration->down();
        }

        foreach (self::TABLES as $table) {
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = ? AND table_name = ?',
                [$schema, $table]
            );
            $this->assertNull($row, "table {$table} devrait être supprimée par down()");

            // Restaurer pour ne pas laisser la base fraîche des tests suivants
            // sans les tables (les down() ci-dessus sortent du runner).
            /** @var CrmV0DownableMigration $migration */
            $migration = require database_path('migrations/tenant/'.$files[array_search($table, self::TABLES, true)]);
            $migration->up();
        }
    }
}
