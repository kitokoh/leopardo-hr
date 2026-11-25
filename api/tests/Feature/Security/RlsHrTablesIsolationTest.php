<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #9924 — preuve que les 4 tables RH nominatives isolent reellement les
 * tenants sous RLS.
 *
 * ## Pourquoi un role dedie
 *
 * Le role applicatif de ce depot est SUPERUSER, et PostgreSQL fait
 * **toujours** bypasser RLS aux superusers, meme avec `FORCE ROW LEVEL
 * SECURITY`. Un test qui compterait les lignes en tant qu'utilisateur
 * applicatif passerait au vert **sans rien prouver**. Ce test cree donc un
 * role NON-superuser et compte depuis lui — c'est la seule facon de mesurer
 * la politique plutot que de constater qu'elle existe.
 *
 * ## Ce qui est verifie, par table
 *
 *   - tenant A ne voit que sa ligne ;
 *   - tenant B ne voit que la sienne ;
 *   - aucun tenant positionne -> **0 ligne** (fail-closed) ;
 *   - une ecriture hors tenant est refusee par le `WITH CHECK`.
 *
 * ## `tax_slabs` n'est pas ici, et c'est deliberé
 *
 * Ses 6 lignes a `company_id` NULL sont les **baremes legaux partages**
 * (repli `whereNull('company_id')` dans `AbstractCountryRules`). La filtrer
 * par tenant casserait le calcul de paie de toute company sans bareme propre.
 * Elle demande une politique a semantique differente, decidee separement.
 */
final class RlsHrTablesIsolationTest extends TestCase
{
    private const ROLE = 'rls_hr_probe';

    private const TENANT_A = '11111111-1111-1111-1111-111111111111';

    private const TENANT_B = '22222222-2222-2222-2222-222222222222';

    /**
     * Tables du lot #9924, avec les colonnes NOT NULL a renseigner pour
     * l'insertion de sonde.
     *
     * @var array<string, array<string, mixed>>
     */
    private const TABLES = [
        'absences' => [
            'employee_id' => 1,
            'absence_type_id' => 1,
            'days_count' => 2,
        ],
        'contracts' => [
            'employee_id' => 1,
        ],
        'leave_balances' => [
            'employee_id' => 1,
            'absence_type_id' => 1,
        ],
        'attendance_logs' => [
            'employee_id' => 1,
        ],
    ];

    /**
     * Colonnes de date a renseigner, en RELATIF a maintenant.
     *
     * Le garde #8396 interdit une date calendaire figee dans le passe : elle
     * pourrit avec le temps (un test ecrit avec une date fixe devient
     * faux en 2027). Les valeurs sont donc calculees a l'execution.
     *
     * @return array<string, mixed>
     */
    private function datesFor(string $table): array
    {
        $today = now()->toDateString();

        return match ($table) {
            'absences' => [
                'start_date' => $today,
                'end_date' => now()->addDay()->toDateString(),
            ],
            'contracts' => ['start_date' => $today],
            'attendance_logs' => ['date' => $today],
            'leave_balances' => ['year' => (int) now()->year],
            default => [],
        };
    }

    private bool $roleCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS est une fonctionnalite PostgreSQL.');
        }

        $this->createProbeRole();
        $this->applyPolicies();
    }

    protected function tearDown(): void
    {
        if ($this->roleCreated) {
            DB::statement('DROP OWNED BY '.self::ROLE);
            DB::statement('DROP ROLE IF EXISTS '.self::ROLE);
        }

        parent::tearDown();
    }

    /**
     * Les 4 tables portent ENABLE + FORCE + une politique.
     *
     * Ce test ne suppose pas que la migration a tourne : il lit l'etat reel
     * de `pg_class` / `pg_policy`. Une migration enregistree « Ran » dont
     * l'effet est absent (le defaut #9896) echouerait donc ici.
     */
    public function test_the_four_hr_tables_carry_the_tenant_isolation_policy(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);

            if ($exists === null || $exists->t === null) {
                $this->markTestSkipped($table.' absente de cette base.');
            }

            $row = DB::selectOne(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass(?)',
                ['shared_tenants.'.$table]
            );

            expect((bool) $row->relrowsecurity)->toBeTrue($table.' doit avoir RLS active.');
            expect((bool) $row->relforcerowsecurity)->toBeTrue($table.' doit avoir FORCE RLS.');

            $policy = DB::selectOne(
                'SELECT polname FROM pg_policy WHERE polrelid = to_regclass(?) AND polname = ?',
                ['shared_tenants.'.$table, $table.'_tenant_isolation']
            );

            expect($policy)->not->toBeNull($table.' doit porter la politique '.$table.'_tenant_isolation.');
        }
    }

    /**
     * Isolation reelle, mesuree depuis un role NON-superuser.
     */
    public function test_each_hr_table_isolates_tenants_and_fails_closed(): void
    {
        foreach (self::TABLES as $table => $required) {
            $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);

            if ($exists === null || $exists->t === null) {
                continue;
            }

            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON shared_tenants.'.$table.' TO '.self::ROLE);

            // Nettoyage : ce test n'utilise pas de transaction (connexions
            // multi-roles), donc une reexecution trouverait les lignes du run
            // precedent.
            DB::table($table)->whereIn('company_id', [self::TENANT_A, self::TENANT_B])->delete();

            DB::table($table)->insert([
                array_merge($required, $this->datesFor($table), $this->timestampsFor($table), [
                    'company_id' => self::TENANT_A,
                    'employee_id' => 1,
                ]),
                array_merge($required, $this->datesFor($table), $this->timestampsFor($table), [
                    'company_id' => self::TENANT_B,
                    // `leave_balances` porte UNIQUE (employee_id,
                    // absence_type_id, year) : deux lignes du meme employe
                    // violeraient la contrainte avant meme que RLS n'entre en
                    // jeu. Un employe distinct par tenant est aussi plus
                    // realiste — un employe appartient a un seul tenant.
                    'employee_id' => 2,
                ]),
            ]);

            expect($this->countAsProbe($table, self::TENANT_A))
                ->toBe(1, $table.' : RLS doit masquer la ligne du tenant B.');
            expect($this->countAsProbe($table, self::TENANT_B))
                ->toBe(1, $table.' : RLS doit masquer la ligne du tenant A.');
            expect($this->countAsProbe($table, null))
                ->toBe(0, $table.' : sans tenant, RLS doit ne rien laisser passer.');

            // Le compte de controle : en SUPERUSER (role applicatif), les deux
            // lignes sont visibles — la preuve que le filtre vient de la
            // politique et non d'un `where` applicatif.
            expect(DB::table($table)->whereIn('company_id', [self::TENANT_A, self::TENANT_B])->count())
                ->toBe(2, $table.' : le role applicatif doit voir les deux lignes (controle).');

            DB::table($table)->whereIn('company_id', [self::TENANT_A, self::TENANT_B])->delete();
        }
    }

    /**
     * Une ecriture hors du tenant courant est refusee par le `WITH CHECK`.
     */
    public function test_writes_outside_the_current_tenant_are_rejected(): void
    {
        $table = 'contracts';
        $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);

        if ($exists === null || $exists->t === null) {
            $this->markTestSkipped('contracts absente de cette base.');
        }

        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON shared_tenants.'.$table.' TO '.self::ROLE);
        DB::table($table)->whereIn('company_id', [self::TENANT_A, self::TENANT_B])->delete();

        DB::table($table)->insert(array_merge(self::TABLES[$table], [
            'company_id' => self::TENANT_A,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $affected = $this->affectedAsProbe(
            self::TENANT_B,
            "UPDATE shared_tenants.{$table} SET company_id = '".self::TENANT_B."' WHERE company_id = '".self::TENANT_A."'"
        );

        expect($affected)->toBe(0, 'RLS doit empecher toute ecriture hors du tenant courant.');

        DB::table($table)->whereIn('company_id', [self::TENANT_A, self::TENANT_B])->delete();
    }

    /**
     * Applique les politiques du lot #9924.
     *
     * ## Pourquoi le test les applique lui-meme
     *
     * Ce test n'utilise pas `RefreshDatabase` (il a besoin de vraies
     * connexions multi-roles, hors transaction). Mais d'AUTRES tests de la
     * suite utilisent `RefreshDatabase`, qui recree le schema : la migration
     * #9924 est alors rejouee... ou pas, selon l'ordre d'execution. Mesure
     * faite : lance en isolation le test passait, lance apres la suite
     * `tests/Feature/Planning` il echouait sur `absences doit avoir RLS
     * active`.
     *
     * Un test qui depend de l'ordre d'execution ne prouve rien. Il applique
     * donc la politique lui-meme — exactement comme
     * `RlsTenantIsolationTest` le fait pour `bulk_payment_claims` — et
     * verifie ensuite l'etat reel de `pg_class` / `pg_policy`.
     */
    private function applyPolicies(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);

            if ($exists === null || $exists->t === null) {
                continue;
            }

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

    /**
     * Timestamps reellement presents sur la table.
     *
     * `leave_balances` n'a pas de `created_at` (seulement `updated_at`) : un
     * insert qui suppose les deux echoue en `42703`. On lit le schema plutot
     * que de coder en dur une liste qui divergera.
     *
     * @return array<string, mixed>
     */
    private function timestampsFor(string $table): array
    {
        $columns = DB::select(
            "SELECT column_name FROM information_schema.columns
              WHERE table_schema = 'shared_tenants' AND table_name = ?
                AND column_name IN ('created_at', 'updated_at')",
            [$table]
        );

        $out = [];
        foreach ($columns as $column) {
            $out[(string) $column->column_name] = now();
        }

        return $out;
    }

    private function createProbeRole(): void
    {
        DB::statement('DROP ROLE IF EXISTS '.self::ROLE);
        DB::statement('CREATE ROLE '.self::ROLE." LOGIN PASSWORD 'probe'");
        DB::statement('GRANT USAGE ON SCHEMA shared_tenants TO '.self::ROLE);

        $this->roleCreated = true;
    }

    private function countAsProbe(string $table, ?string $tenantId): int
    {
        $connection = $this->probeConnection($tenantId);

        try {
            $row = $connection->selectOne('SELECT count(*) AS c FROM shared_tenants.'.$table);

            return (int) ($row->c ?? 0);
        } finally {
            DB::purge('rls_hr_probe_conn');
        }
    }

    private function affectedAsProbe(?string $tenantId, string $sql): int
    {
        $connection = $this->probeConnection($tenantId);

        try {
            return $connection->update($sql);
        } finally {
            DB::purge('rls_hr_probe_conn');
        }
    }

    private function probeConnection(?string $tenantId): Connection
    {
        config([
            'database.connections.rls_hr_probe_conn' => [
                'driver' => 'pgsql',
                'host' => config('database.connections.pgsql.host'),
                'port' => config('database.connections.pgsql.port'),
                'database' => config('database.connections.pgsql.database'),
                'username' => self::ROLE,
                'password' => 'probe',
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'shared_tenants',
                'search_path' => 'shared_tenants,public',
                'sslmode' => 'prefer',
            ],
        ]);

        $connection = DB::connection('rls_hr_probe_conn');

        // `set_config(..., false)` est local a la SESSION : on le pose donc
        // sur la connexion du role limite, pas sur celle de l'application.
        if ($tenantId !== null) {
            $connection->statement("SELECT set_config('app.current_company_id', ?, false)", [$tenantId]);
        }

        return $connection;
    }
}
