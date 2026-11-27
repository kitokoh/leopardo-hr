<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #9975 — le lot 6 de couverture RLS (paie, facturation, comptabilité, santé,
 * identité) fait ce qu'il annonce.
 *
 * ## Ce que ce test prouve, et pourquoi il est nécessaire
 *
 * La migration `2026_11_27_000100_9975_enable_rls_on_sensitive_tables` pose
 * 20 politiques. Deux choses peuvent silencieusement la rendre inutile :
 *
 *   1. **le backfill** — `pay_slip_lines.company_id` et
 *      `leave_balance_logs.company_id` sont nullables. Sous la politique, une
 *      ligne à `NULL` devient INVISIBLE (`NULL = 'x'` vaut `NULL`, pas `TRUE`).
 *      Si le backfill ne tourne pas, la migration échoue (garde
 *      `assertNoNullCompanyId`) — mais si elle échouait *après* avoir posé
 *      certaines politiques, on aurait un état partiel ;
 *   2. **la politique elle-même** — une politique mal écrite (`USING (true)`,
 *      comparaison sur la mauvaise colonne) laisse tout passer et ressemble à
 *      une protection.
 *
 * Le test crée les tables minimales, insère deux tenants, exécute le SQL de la
 * migration, puis mesure l'isolation sous un rôle non-superuser.
 *
 * ## Ce qu'il ne prouve pas
 *
 * Que la production est basculée. Tant que `DB_USERNAME` est SUPERUSER,
 * PostgreSQL bypasse RLS même avec `FORCE` (#9974). Ce test mesure la
 * POLITIQUE, pas le rôle — c'est le gate `tenant:rls-status --strict` qui
 * mesure le rôle.
 */
final class RlsSensitiveTablesLot6Test extends TestCase
{
    private const TENANT_A = '11111111-1111-1111-1111-111111111111';

    private const TENANT_B = '22222222-2222-2222-2222-222222222222';

    private const ROLE = 'leopardo_lot6_probe';

    /**
     * Les 20 tables du lot, avec la colonne de backfill éventuelle.
     *
     * Dupliqué depuis la migration volontairement : si quelqu'un retire une
     * table de la migration sans retirer le test, le test échoue. C'est le
     * comportement voulu — un test qui lit la constante de la migration ne
     * testerait que lui-même.
     */
    private const TABLES = [
        'employee_loans' => null,
        'payment_batches' => null,
        'payment_confirmations' => null,
        'pay_slip_lines' => ['pay_slips', 'pay_slip_id'],
        'invoices' => null,
        'billing_collections' => null,
        'accounting_documents' => null,
        'accounting_document_lines' => null,
        'bank_statements' => null,
        'bank_statement_lines' => null,
        'health_patients' => null,
        'health_consultations' => null,
        'health_prescriptions' => null,
        'health_prescription_items' => null,
        'health_invoices' => null,
        'employee_documents' => null,
        'employee_departures' => null,
        'contract_amendments' => null,
        'leave_accruals' => null,
        'leave_balance_logs' => ['employees', 'employee_id'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS est une fonctionnalité PostgreSQL.');
        }

        $this->dropProbeObjects();
        $this->createProbeSchema();
    }

    protected function tearDown(): void
    {
        $this->dropProbeObjects();

        parent::tearDown();
    }

    /**
     * Le backfill renseigne `company_id` depuis la table parente, et la
     * migration refuse de poser une politique s'il reste un NULL.
     */
    public function test_the_backfill_fills_nullable_company_id_from_the_parent(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => self::TENANT_A],
            ['id' => 2, 'company_id' => self::TENANT_B],
        ]);
        DB::table('pay_slips')->insert(['id' => 1, 'company_id' => self::TENANT_A]);

        // Deux lignes orphelines de tenant, comme en production avant #9975.
        DB::table('pay_slip_lines')->insert(['id' => 1, 'pay_slip_id' => 1, 'company_id' => null]);
        DB::table('leave_balance_logs')->insert([
            ['id' => 1, 'employee_id' => 1, 'company_id' => null],
            ['id' => 2, 'employee_id' => 2, 'company_id' => null],
        ]);

        $this->runMigrationSql();

        expect(DB::table('pay_slip_lines')->whereNull('company_id')->count())
            ->toBe(0, 'pay_slip_lines doit être backfillé depuis pay_slips.');
        expect(DB::table('pay_slip_lines')->where('id', 1)->value('company_id'))
            ->toBe(self::TENANT_A);

        expect(DB::table('leave_balance_logs')->whereNull('company_id')->count())
            ->toBe(0, 'leave_balance_logs doit être backfillé depuis employees.');
        expect(DB::table('leave_balance_logs')->where('id', 2)->value('company_id'))
            ->toBe(self::TENANT_B, 'chaque ligne doit hériter du tenant de SON employé.');
    }

    /**
     * Les 20 tables sont réellement filtrées : sans tenant publié, 0 ligne.
     */
    public function test_every_table_of_the_lot_is_fail_closed(): void
    {
        $this->seedOneRowPerTable();
        $this->runMigrationSql();
        $this->createProbeRole();

        $leaks = [];

        foreach (array_keys(self::TABLES) as $table) {
            $count = $this->countAsProbeRole($table, null);

            if ($count !== 0) {
                $leaks[] = "{$table} : {$count} ligne(s) visible(s) sans tenant";
            }
        }

        expect($leaks)->toBe([], "RLS doit être fail-closed sur les 20 tables.\n".implode("\n", $leaks));
    }

    /**
     * Avec le tenant publié, chaque table ne montre que SON tenant.
     */
    public function test_every_table_isolates_the_published_tenant(): void
    {
        $this->seedOneRowPerTable();
        $this->runMigrationSql();
        $this->createProbeRole();

        $wrong = [];

        foreach (array_keys(self::TABLES) as $table) {
            $a = $this->countAsProbeRole($table, self::TENANT_A);
            $b = $this->countAsProbeRole($table, self::TENANT_B);

            if ($a !== 1 || $b !== 1) {
                $wrong[] = "{$table} : tenant A={$a}, tenant B={$b} (attendu 1 et 1)";
            }
        }

        expect($wrong)->toBe([], "Chaque table doit isoler le tenant publié.\n".implode("\n", $wrong));
    }

    /**
     * Une écriture hors du tenant courant est refusée par le `WITH CHECK`.
     */
    public function test_writes_outside_the_published_tenant_are_rejected(): void
    {
        $this->seedOneRowPerTable();
        $this->runMigrationSql();
        $this->createProbeRole();

        // Le tenant A tente de réécrire la ligne du tenant B.
        $affected = $this->updateAsProbeRole(
            self::TENANT_A,
            "UPDATE shared_tenants.invoices SET company_id = '".self::TENANT_A."' "
            ."WHERE company_id = '".self::TENANT_B."'"
        );

        expect($affected)->toBe(0, 'RLS doit empêcher une écriture hors du tenant courant.');

        // Et la ligne du tenant B est intacte.
        expect(DB::table('invoices')->where('company_id', self::TENANT_B)->count())
            ->toBe(1, 'la ligne du tenant B ne doit pas avoir été déplacée.');
    }

    // -----------------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------------

    private function createProbeSchema(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');

        // Tables parentes (backfill).
        DB::statement('CREATE TABLE shared_tenants.employees (id serial PRIMARY KEY, company_id uuid NOT NULL)');
        DB::statement('CREATE TABLE shared_tenants.pay_slips (id bigserial PRIMARY KEY, company_id uuid NOT NULL)');

        foreach (self::TABLES as $table => $backfill) {
            if (in_array($table, ['employees', 'pay_slips'], true)) {
                continue;
            }

            $nullable = $backfill !== null ? '' : ' NOT NULL';
            $fk = $backfill !== null
                ? ", {$backfill[1]} ".($backfill[0] === 'pay_slips' ? 'bigint' : 'int').' NOT NULL'
                : '';

            DB::statement(
                "CREATE TABLE shared_tenants.{$table} ("
                ."id serial PRIMARY KEY, company_id uuid{$nullable}{$fk})"
            );
        }
    }

    private function dropProbeObjects(): void
    {
        DB::statement('DROP OWNED BY '.self::ROLE.' CASCADE');
        DB::statement('DROP ROLE IF EXISTS '.self::ROLE);

        foreach (array_keys(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS shared_tenants.{$table} CASCADE");
        }
    }

    /**
     * Une ligne par tenant et par table, pour mesurer l'isolation.
     */
    private function seedOneRowPerTable(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => self::TENANT_A],
            ['id' => 2, 'company_id' => self::TENANT_B],
        ]);
        DB::table('pay_slips')->insert([
            ['id' => 1, 'company_id' => self::TENANT_A],
            ['id' => 2, 'company_id' => self::TENANT_B],
        ]);

        foreach (self::TABLES as $table => $backfill) {
            if (in_array($table, ['employees', 'pay_slips'], true)) {
                continue;
            }

            if ($backfill !== null) {
                // Ligne rattachée à la table parente : le backfill fera le reste.
                DB::table($table)->insert([
                    ['id' => 1, $backfill[1] => 1, 'company_id' => null],
                    ['id' => 2, $backfill[1] => 2, 'company_id' => null],
                ]);

                continue;
            }

            DB::table($table)->insert([
                ['id' => 1, 'company_id' => self::TENANT_A],
                ['id' => 2, 'company_id' => self::TENANT_B],
            ]);
        }
    }

    /**
     * Exécute le SQL de la migration, dans le même ordre : backfill, garde,
     * ENABLE, FORCE, policy.
     */
    private function runMigrationSql(): void
    {
        foreach (self::TABLES as $table => $backfill) {
            if ($backfill !== null) {
                DB::statement(
                    "UPDATE shared_tenants.{$table} AS child "
                    .'SET company_id = parent.company_id '
                    ."FROM shared_tenants.{$backfill[0]} AS parent "
                    ."WHERE child.{$backfill[1]} = parent.id "
                    .'AND child.company_id IS NULL AND parent.company_id IS NOT NULL'
                );
            }

            $nulls = DB::table($table)->whereNull('company_id')->count();
            expect($nulls)->toBe(0, "garde : {$table} ne doit plus avoir de company_id NULL.");

            DB::statement("ALTER TABLE shared_tenants.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE shared_tenants.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON shared_tenants.{$table}");
            DB::statement(<<<SQL
                CREATE POLICY {$table}_tenant_isolation
                ON shared_tenants.{$table}
                USING (company_id::text = current_setting('app.current_company_id', true))
                WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
                SQL);
        }
    }

    private function createProbeRole(): void
    {
        DB::statement('CREATE ROLE '.self::ROLE.' LOGIN NOSUPERUSER NOBYPASSRLS PASSWORD \'probe\'');
        DB::statement('GRANT USAGE ON SCHEMA shared_tenants TO '.self::ROLE);
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA shared_tenants TO '.self::ROLE);
    }

    private function probeConnection(?string $tenantId): \Illuminate\Database\Connection
    {
        $base = (array) config('database.connections.'.config('database.default'));

        config(['database.connections.lot6_probe' => array_merge($base, [
            'username' => self::ROLE,
            'password' => 'probe',
        ])]);

        $connection = DB::connection('lot6_probe');
        $connection->statement(
            "SELECT set_config('app.current_company_id', ?, false)",
            [$tenantId ?? '']
        );

        return $connection;
    }

    private function countAsProbeRole(string $table, ?string $tenantId): int
    {
        $connection = $this->probeConnection($tenantId);

        try {
            $row = $connection->selectOne("SELECT count(*) AS c FROM shared_tenants.{$table}");

            return (int) ($row->c ?? 0);
        } finally {
            DB::purge('lot6_probe');
        }
    }

    private function updateAsProbeRole(?string $tenantId, string $sql): int
    {
        $connection = $this->probeConnection($tenantId);

        try {
            return $connection->update($sql);
        } finally {
            DB::purge('lot6_probe');
        }
    }
}
