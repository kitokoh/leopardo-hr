<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #9879 (R8) — preuve que la politique RLS isole réellement les tenants.
 *
 * Constat de la revue : aucune migration n'activait RLS — l'isolation
 * reposait uniquement sur le search_path et le scope Eloquent. Une requête
 * brute (`DB::select`) pouvait donc lire les lignes de tous les tenants.
 *
 * ⚠️ POINT CRUCIAL mesuré dans ce dépôt : le rôle applicatif est SUPERUSER.
 * PostgreSQL fait TOUJOURS bypasser RLS aux superusers, MÊME avec
 * `FORCE ROW LEVEL SECURITY`. Un test qui se contente d'écrire deux lignes
 * puis de compter en tant qu'utilisateur applicatif ne prouverait donc RIEN.
 *
 * Ce test crée un rôle NON-superuser dédié, lui donne les droits sur la
 * table, exécute la politique de la migration, et vérifie depuis ce rôle :
 *   - tenant A ne voit que la ligne A ;
 *   - tenant B ne voit que la ligne B ;
 *   - tenant NON POSITIONNÉ ne voit RIEN (fail-closed).
 *
 * Il documente aussi la limite : tant que l'application se connecte en
 * SUPERUSER, RLS est inopérant. C'est un prérequis de déploiement, pas un
 * détail — d'où l'ADR.
 */
class RlsTenantIsolationTest extends TestCase
{
    private const ROLE = 'rls_isolation_probe';

    private const TENANT_A = '11111111-1111-1111-1111-111111111111';

    private const TENANT_B = '22222222-2222-2222-2222-222222222222';

    private bool $roleCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS est une fonctionnalité PostgreSQL.');
        }

        if (! $this->tableExists()) {
            $this->markTestSkipped('bulk_payment_claims absente de cette base.');
        }

        $this->createProbeRole();

        // Ce test n'utilise volontairement PAS RefreshDatabase (il a besoin de
        // vraies connexions multi-rôles, hors transaction). On nettoie donc
        // explicitement : sans cela, une réexécution dans la même base trouve
        // les lignes du run précédent et viole UNIQUE (payroll_run_id).
        DB::table('bulk_payment_claims')->whereIn('payroll_run_id', [900001, 900002, 900003])->delete();
    }

    protected function tearDown(): void
    {
        if ($this->roleCreated) {
            $this->resetPolicy();
            DB::statement('DROP OWNED BY '.self::ROLE);
            DB::statement('DROP ROLE IF EXISTS '.self::ROLE);
        }

        parent::tearDown();
    }

    /**
     * #9896 — le nettoyage du test ne doit pas DÉSACTIVER la protection.
     *
     * Constat mesuré avec `php artisan tenant:rls-status` : après une
     * exécution de cette suite, `bulk_payment_claims` ressortait avec
     * `relrowsecurity = false` alors que la migration #9879 était enregistrée
     * comme passée. Cause : `resetPolicy()` faisait
     * `ALTER TABLE ... DISABLE ROW LEVEL SECURITY` en tearDown.
     *
     * Le test applique lui-même la politique (il n'utilise pas
     * RefreshDatabase, il a besoin de vraies connexions multi-rôles), donc
     * son nettoyage doit RESTAURER l'état de la migration — pas le supprimer.
     * Un test qui laisse la base moins protégée qu'il ne l'a trouvée est un
     * défaut de sécurité silencieux : la suite passe au vert et la table
     * reste ouverte.
     */
    public function test_teardown_restores_the_migration_policy_instead_of_disabling_it(): void
    {
        // Simule ce que fait tearDown après un run : la politique doit être
        // reposée, pas retirée.
        $this->applyPolicy();
        $this->resetPolicy();

        $row = DB::selectOne(
            'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass(?)',
            ['shared_tenants.bulk_payment_claims']
        );

        expect((bool) $row->relrowsecurity)->toBeTrue(
            'Après nettoyage, bulk_payment_claims doit rester protégée (RLS activée).'
        );
        expect((bool) $row->relforcerowsecurity)->toBeTrue(
            'Après nettoyage, FORCE RLS doit rester posé.'
        );

        $policy = DB::selectOne(
            'SELECT 1 AS ok FROM pg_policy WHERE polrelid = to_regclass(?) AND polname = ?',
            ['shared_tenants.bulk_payment_claims', 'bulk_payment_claims_tenant_isolation']
        );

        expect($policy)->not->toBeNull(
            'Après nettoyage, la politique de la migration doit être reposée.'
        );
    }

    public function test_rls_isolates_tenants_and_fails_closed_without_tenant(): void
    {
        $this->applyPolicy();

        DB::table('bulk_payment_claims')->insert([
            [
                'payroll_run_id' => 900001,
                'company_id' => self::TENANT_A,
                'status' => 'starting',
                'expires_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'payroll_run_id' => 900002,
                'company_id' => self::TENANT_B,
                'status' => 'starting',
                'expires_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Tenant A : une seule ligne, la sienne.
        $tenantA = $this->countAsProbe(self::TENANT_A);
        expect($tenantA)->toBe(1, 'RLS doit masquer la ligne du tenant B.');

        // Tenant B : une seule ligne, la sienne.
        $tenantB = $this->countAsProbe(self::TENANT_B);
        expect($tenantB)->toBe(1, 'RLS doit masquer la ligne du tenant A.');

        // Aucun tenant positionné : fail-closed, zéro ligne visible.
        $noTenant = $this->countAsProbe(null);
        expect($noTenant)->toBe(0, 'Sans tenant positionné, RLS doit ne rien laisser passer.');

        // Le compte de contrôle : sans RLS (rôle superuser applicatif), on voit
        // bien les deux lignes — la preuve que le filtre vient de la politique.
        expect(DB::table('bulk_payment_claims')->count())->toBe(2);
    }

    public function test_writes_outside_the_current_tenant_are_rejected(): void
    {
        $this->applyPolicy();

        // Le rôle applicatif (superuser) pose une ligne pour le tenant A.
        DB::table('bulk_payment_claims')->insert([
            'payroll_run_id' => 900003,
            'company_id' => self::TENANT_A,
            'status' => 'starting',
            'expires_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Le rôle limité, positionné sur le tenant B, ne peut pas modifier la
        // ligne du tenant A : le WITH CHECK la rend invisible.
        $affected = $this->affectedAsProbe(
            self::TENANT_B,
            'UPDATE shared_tenants.bulk_payment_claims SET status = \'hijacked\' WHERE payroll_run_id = 900003'
        );

        expect($affected)->toBe(0, 'RLS doit empêcher toute écriture hors du tenant courant.');

        // La ligne est intacte.
        $status = DB::table('bulk_payment_claims')->where('payroll_run_id', 900003)->value('status');
        expect($status)->toBe('starting');
    }

    private function tableExists(): bool
    {
        $row = DB::selectOne("SELECT to_regclass('shared_tenants.bulk_payment_claims') AS t");

        return $row !== null && $row->t !== null;
    }

    /**
     * #9889 — les tables de paie sont couvertes par la MEME politique.
     *
     * Ce test ne se contente pas de verifier que la migration a tourne : il
     * verifie que la politique est bien posee sur les deux tables, et que le
     * cablage `app.current_company_id` de `TenantManager` la rend effective.
     * Sans ce cablage, `current_setting(..., true)` renvoie NULL et la
     * comparaison vaut NULL — la table serait illisible, pas protegee.
     */
    public function test_payroll_tables_carry_the_tenant_isolation_policy(): void
    {
        foreach (['pay_slips', 'payroll_runs'] as $table) {
            $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);
            if ($exists === null || $exists->t === null) {
                $this->markTestSkipped($table.' absente de cette base.');
            }

            $row = DB::selectOne(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass(?)',
                ['shared_tenants.'.$table]
            );

            expect((bool) $row->relrowsecurity)->toBeTrue($table.' doit avoir RLS active.');
            expect((bool) $row->relforcerowsecurity)->toBeTrue($table.' doit avoir FORCE RLS (sinon le proprietaire contourne).');

            $policy = DB::selectOne(
                'SELECT polname FROM pg_policy WHERE polrelid = to_regclass(?) AND polname = ?',
                ['shared_tenants.'.$table, $table.'_tenant_isolation']
            );

            expect($policy)->not->toBeNull($table.' doit porter la politique '.$table.'_tenant_isolation.');
        }
    }

    /**
     * #9889 — `TenantManager` publie le tenant dans la session PostgreSQL.
     *
     * C'est le maillon qui rend RLS effectif : sans lui, la politique existe
     * mais ne compare jamais rien. On verifie la variable de session, pas
     * l'implementation.
     */
    public function test_tenant_manager_publishes_the_company_to_the_session(): void
    {
        // La company est creee ici plutot que lue : un test qui se `skip`
        // faute de donnee ne prouve rien, et c'est exactement le maillon dont
        // depend toute la politique RLS.
        $company = \App\Core\Tenant\Domain\Models\Company::factory()->create();

        $manager = app(\App\Core\Tenant\TenantManager::class);

        $manager->setTenant($company);

        $current = DB::selectOne("SELECT current_setting('app.current_company_id', true) AS c");
        expect((string) $current->c)->toBe((string) $company->id, 'setTenant() doit publier le tenant pour RLS.');

        $manager->clearTenant();

        $cleared = DB::selectOne("SELECT current_setting('app.current_company_id', true) AS c");
        expect((string) $cleared->c)->toBe('', 'clearTenant() doit effacer le tenant (fail-closed).');
    }

    private function createProbeRole(): void
    {
        DB::statement('DROP ROLE IF EXISTS '.self::ROLE);
        DB::statement('CREATE ROLE '.self::ROLE.' LOGIN PASSWORD \'probe\'');
        DB::statement('GRANT USAGE ON SCHEMA shared_tenants TO '.self::ROLE);
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON shared_tenants.bulk_payment_claims TO '.self::ROLE);

        $this->roleCreated = true;
    }

    /**
     * Applique exactement la politique de la migration #9879.
     */
    private function applyPolicy(): void
    {
        DB::statement('ALTER TABLE shared_tenants.bulk_payment_claims ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE shared_tenants.bulk_payment_claims FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS bulk_payment_claims_tenant_isolation ON shared_tenants.bulk_payment_claims');
        DB::statement(<<<'SQL'
            CREATE POLICY bulk_payment_claims_tenant_isolation
            ON shared_tenants.bulk_payment_claims
            USING (company_id::text = current_setting('app.current_company_id', true))
            WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
            SQL);
    }

    /**
     * #9896 — RESTAURE l'état de la migration #9879, ne le supprime pas.
     *
     * La version précédente faisait `DISABLE ROW LEVEL SECURITY` : après un
     * run de la suite, la table restait ouverte alors que la migration était
     * enregistrée comme passée. Le test applique la politique lui-même, donc
     * son nettoyage doit laisser la table dans l'état attendu par la
     * migration — c'est-à-dire protégée.
     */
    private function resetPolicy(): void
    {
        DB::statement('ALTER TABLE shared_tenants.bulk_payment_claims ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE shared_tenants.bulk_payment_claims FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS bulk_payment_claims_tenant_isolation ON shared_tenants.bulk_payment_claims');
        DB::statement(<<<'SQL'
            CREATE POLICY bulk_payment_claims_tenant_isolation
            ON shared_tenants.bulk_payment_claims
            USING (company_id::text = current_setting('app.current_company_id', true))
            WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
            SQL);
    }

    /**
     * Exécute un COUNT depuis le rôle NON-superuser (sinon RLS est bypassé).
     */
    private function countAsProbe(?string $tenantId): int
    {
        $connection = $this->probeConnection($tenantId);

        try {
            $row = $connection->selectOne('SELECT count(*) AS c FROM shared_tenants.bulk_payment_claims');

            return (int) ($row->c ?? 0);
        } finally {
            DB::purge('rls_probe');
        }
    }

    private function affectedAsProbe(?string $tenantId, string $sql): int
    {
        $connection = $this->probeConnection($tenantId);

        try {
            return $connection->update($sql);
        } finally {
            DB::purge('rls_probe');
        }
    }

    private function probeConnection(?string $tenantId): \Illuminate\Database\Connection
    {
        if ($tenantId !== null) {
            DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$tenantId]);
        }

        config([
            'database.connections.rls_probe' => [
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

        $connection = DB::connection('rls_probe');

        // set_config(..., false) est local à la SESSION ; on le repose donc
        // sur la connexion du rôle limité.
        if ($tenantId !== null) {
            $connection->statement("SELECT set_config('app.current_company_id', ?, false)", [$tenantId]);
        }

        return $connection;
    }
}
