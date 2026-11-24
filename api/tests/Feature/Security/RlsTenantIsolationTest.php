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

    private function resetPolicy(): void
    {
        DB::statement('DROP POLICY IF EXISTS bulk_payment_claims_tenant_isolation ON shared_tenants.bulk_payment_claims');
        DB::statement('ALTER TABLE shared_tenants.bulk_payment_claims NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE shared_tenants.bulk_payment_claims DISABLE ROW LEVEL SECURITY');
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
