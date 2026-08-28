<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5708 (CRM-V0-04) — migrations crm_accounts / crm_contacts.
 *
 * Verrouille :
 *   1. le placement des tables dans le schéma tenant (shared_tenants) ;
 *   2. `company_id` NON nullable (isolation BelongsToCompany) ;
 *   3. les contraintes CHECK nommées (status / source) ;
 *   4. le contact primaire UNIQUE par compte (index partiel) ;
 *   5. l'impossibilité STRUCTURELLE des relations cross-tenant (FK
 *      composite account_id + company_id, contacts→accounts) ;
 *   6. la FK additive crm_opportunities.account_id → crm_accounts (quand la
 *      table opportunités est présente, issue #5709) ;
 *   7. l'idempotence des gardes F-17 (up() rejoué) et le cycle down()/up().
 */
class CrmAccountsContactsMigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @var list<string> */
    private const TABLES = ['crm_accounts', 'crm_contacts'];

    /** @var list<string> */
    private const MIGRATIONS = [
        '2026_08_28_000301_5708_create_crm_accounts_table',
        '2026_08_28_000302_5708_create_crm_contacts_table',
    ];

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

    private function migration(string $basename): Migration
    {
        $path = database_path("migrations/tenant/{$basename}.php");
        $this->assertFileExists($path);

        $migration = require $path;

        $this->assertInstanceOf(Migration::class, $migration);

        /** @var Migration $migration */
        return $migration;
    }

    public function test_tables_are_created_in_tenant_schema(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table), "{$table} doit être créée dans shared_tenants");
        }
    }

    public function test_company_id_is_not_nullable(): void
    {
        foreach (self::TABLES as $table) {
            $row = DB::selectOne(
                'SELECT is_nullable
                   FROM information_schema.columns
                  WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                ['shared_tenants', $table, 'company_id']
            );

            $this->assertNotNull($row, "{$table}.company_id absente");
            $this->assertSame('NO', (string) $row->is_nullable, "{$table}.company_id doit être NON nullable");
        }
    }

    public function test_account_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        DB::table('crm_accounts')->insert([
            'company_id' => $this->newCompany()->id,
            'name' => 'Compte invalide',
            'status' => 'bogus-status',
        ]);
    }

    public function test_account_source_check_rejects_unknown_source(): void
    {
        $this->expectException(QueryException::class);

        DB::table('crm_accounts')->insert([
            'company_id' => $this->newCompany()->id,
            'name' => 'Compte invalide',
            'source' => 'carrier-pigeon',
        ]);
    }

    public function test_contact_status_check_rejects_unknown_status(): void
    {
        $company = Company::factory()->create();
        $accountId = $this->createAccount($company->id, 'Compte A');

        $this->expectException(QueryException::class);

        DB::table('crm_contacts')->insert([
            'company_id' => $company->id,
            'account_id' => $accountId,
            'first_name' => 'Invalide',
            'status' => 'bogus-status',
        ]);
    }

    public function test_cross_tenant_contact_reference_is_rejected_by_database(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $accountAId = $this->createAccount($companyA->id, 'Compte tenant A');

        // Contact du tenant B rattaché au compte du tenant A : la FK composite
        // (account_id, company_id) doit rejeter l'insertion.
        $this->expectException(QueryException::class);

        DB::table('crm_contacts')->insert([
            'company_id' => $companyB->id,
            'account_id' => $accountAId,
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
        ]);
    }

    public function test_only_one_primary_contact_per_account(): void
    {
        $company = Company::factory()->create();
        $accountId = $this->createAccount($company->id, 'Compte A');

        DB::table('crm_contacts')->insert([
            'company_id' => $company->id,
            'account_id' => $accountId,
            'first_name' => 'Primaire',
            'is_primary' => true,
        ]);

        $this->expectException(QueryException::class);

        DB::table('crm_contacts')->insert([
            'company_id' => $company->id,
            'account_id' => $accountId,
            'first_name' => 'Second primaire',
            'is_primary' => true,
        ]);
    }

    public function test_primary_contact_is_scoped_per_account(): void
    {
        $company = Company::factory()->create();
        $accountAId = $this->createAccount($company->id, 'Compte A');
        $accountBId = $this->createAccount($company->id, 'Compte B');

        DB::table('crm_contacts')->insert([
            'company_id' => $company->id,
            'account_id' => $accountAId,
            'first_name' => 'Primaire A',
            'is_primary' => true,
        ]);

        // Un primaire sur un AUTRE compte du même tenant reste autorisé.
        DB::table('crm_contacts')->insert([
            'company_id' => $company->id,
            'account_id' => $accountBId,
            'first_name' => 'Primaire B',
            'is_primary' => true,
        ]);

        $this->assertSame(2, DB::table('crm_contacts')->where('is_primary', true)->count());
    }

    public function test_opportunity_account_fk_is_added_when_opportunities_exist(): void
    {
        if (! schemaTableExists('crm_opportunities')) {
            $this->markTestSkipped('crm_opportunities non migrée (PR #5709 non mergée) — FK additive non posée.');
        }

        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $accountAId = $this->createAccount($companyA->id, 'Compte tenant A');

        $pipelineId = DB::table('crm_pipelines')->insertGetId([
            'company_id' => $companyA->id,
            'name' => 'Pipeline A',
        ]);
        $stageId = DB::table('crm_pipeline_stages')->insertGetId([
            'company_id' => $companyA->id,
            'pipeline_id' => $pipelineId,
            'name' => 'Étape A',
            'position' => 0,
        ]);

        $this->expectException(QueryException::class);

        // Opportunité du tenant B référençant un compte du tenant A.
        DB::table('crm_opportunities')->insert([
            'company_id' => $companyB->id,
            'pipeline_id' => $pipelineId,
            'stage_id' => $stageId,
            'name' => 'Opportunité cross-tenant',
            'account_id' => $accountAId,
        ]);
    }

    public function test_up_is_idempotent(): void
    {
        foreach (self::MIGRATIONS as $basename) {
            $this->migration($basename)->up();
        }

        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table));
        }
    }

    public function test_rollback_then_remigrate_cycle(): void
    {
        foreach (array_reverse(self::MIGRATIONS) as $basename) {
            $this->migration($basename)->down();
        }

        foreach (self::TABLES as $table) {
            $this->assertNull($this->tableSchema($table), "{$table} doit être supprimée par down()");
        }

        foreach (self::MIGRATIONS as $basename) {
            $this->migration($basename)->up();
        }

        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table), "{$table} doit être recréée par up()");
        }
    }


    private function newCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function createAccount(string $companyId, string $name): int
    {
        $id = DB::table('crm_accounts')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $id;
    }
}
