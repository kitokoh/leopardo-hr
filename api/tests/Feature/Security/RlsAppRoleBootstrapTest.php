<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #9974 — le script de bascule du rôle applicatif RLS est EXÉCUTABLE et son
 * effet est celui annoncé.
 *
 * ## Pourquoi ce test existe
 *
 * `database/scripts/rls/create-app-role.sql` est la procédure de bascule de
 * l'ADR-9879 : sans elle, `DB_USERNAME` reste le rôle SUPERUSER et les
 * politiques RLS ne filtrent rien. La version livrée par #9896 écrivait
 * `:'app_password'` DANS un bloc `DO $$ … $$` — psql n'interpole pas ses
 * variables dans une chaîne dollar-quotée, le script échouait en
 * `syntax error at or near ":"`.
 *
 * Autrement dit : **la procédure de sécurité documentée ne pouvait pas
 * s'exécuter**, et rien ne le signalait. C'est exactement le mode de
 * défaillance que l'audit reproche au dépôt — une garantie annoncée, jamais
 * mesurée.
 *
 * Ce test exécute le script pour de vrai, puis mesure son effet :
 *
 *   - le rôle créé n'est ni SUPERUSER ni BYPASSRLS ;
 *   - une requête sans `app.current_company_id` publié renvoie **0 ligne**
 *     (fail-closed) ;
 *   - une requête avec le tenant publié ne voit que ce tenant ;
 *   - une écriture hors tenant est refusée par le `WITH CHECK` ;
 *   - le script est idempotent (rejouable pour une rotation de mot de passe).
 *
 * ## Ce qu'il ne prouve pas
 *
 * Il ne prouve pas que la PRODUCTION est basculée : c'est le rôle du gate
 * `tenant:rls-status --strict` dans `docker-entrypoint.sh`. Il prouve que la
 * procédure de bascule fait ce qu'elle annonce — ce qui manquait.
 */
final class RlsAppRoleBootstrapTest extends TestCase
{
    /**
     * Nom de rôle UNIQUE PAR BASE DE TEST.
     *
     * Un rôle PostgreSQL est CLUSTER-WIDE : deux bases sur la même instance
     * partagent le même catalogue de rôles. En CI parallèle (4 workers), un
     * worker supprimait `leopardo_app` pendant qu'un autre s'y connectait —
     * `password authentication failed` aléatoire, mesuré en CI. Dériver le nom
     * du nom de base rend chaque worker hermétique.
     */
    private string $role = 'leopardo_app';

    private const TENANT_A = '11111111-1111-1111-1111-111111111111';

    private const TENANT_B = '22222222-2222-2222-2222-222222222222';

    private const TABLE = 'rls_bootstrap_probe';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS est une fonctionnalité PostgreSQL.');
        }

        // Le script exige CREATEROLE/SUPERUSER : le rôle de test l'est.
        $role = DB::selectOne('SELECT rolsuper, rolcreaterole FROM pg_roles WHERE rolname = current_user');
        if (! $role->rolsuper && ! $role->rolcreaterole) {
            $this->markTestSkipped('Le rôle de test ne peut pas créer de rôle.');
        }

        // `psql` est le SEUL moyen d'exécuter le script tel qu'un opérateur
        // l'exécutera (variables psql, `\gexec`, `\if`). Sans lui, le test se
        // sautait SILENCIEUSEMENT en CI — mesuré : le runner GitHub n'installe
        // pas postgresql-client, donc `RlsAppRoleBootstrapTest` n'a jamais
        // tourné, et la procédure de bascule restait non vérifiée. C'est
        // exactement le défaut que l'audit reproche au dépôt : une garantie
        // annoncée, jamais mesurée.
        //
        // On ÉCHOUE au lieu de sauter : un test de sécurité qui ne s'exécute
        // pas doit être visible. `postgresql-client` est installé par
        // `.github/actions/setup-backend-db` (étape dédiée).
        if (trim((string) shell_exec('command -v psql 2>/dev/null')) === '') {
            $this->fail(
                'psql introuvable : le script de bascule RLS ne peut pas être exécuté. '
                .'Installer postgresql-client (voir .github/actions/setup-backend-db). '
                .'Ce test ne doit PAS être sauté : il vérifie la procédure de bascule.'
            );
        }

        $this->role = $this->uniqueRoleName();

        $this->dropRole();
        $this->createProbeTable();
    }

    /**
     * `leopardo_app_<base>` : 63 octets max (limite PostgreSQL), caractères
     * sûrs uniquement.
     */
    private function uniqueRoleName(): string
    {
        $db = (string) config('database.connections.'.config('database.default').'.database');
        $suffix = preg_replace('/[^a-z0-9_]/', '_', strtolower($db)) ?? 'test';

        return substr('leopardo_app_'.$suffix, 0, 63);
    }

    protected function tearDown(): void
    {
        $this->dropProbeTable();
        $this->dropRole();

        parent::tearDown();
    }

    /**
     * Le script s'exécute sans erreur et produit un rôle non-bypass.
     */
    public function test_the_bootstrap_script_creates_a_non_bypassing_role(): void
    {
        $this->runBootstrapScript('first-password');

        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = ?',
            [$this->role]
        );

        expect($role)->not->toBeNull('le script doit créer le rôle '.$this->role.'.');
        expect((bool) $role->rolsuper)->toBeFalse('le rôle applicatif ne doit PAS être SUPERUSER.');
        expect((bool) $role->rolbypassrls)->toBeFalse('le rôle applicatif ne doit PAS porter BYPASSRLS.');
        expect((bool) $role->rolcreatedb)->toBeFalse('moindre privilège : NOCREATEDB.');
        expect((bool) $role->rolcreaterole)->toBeFalse('moindre privilège : NOCREATEROLE.');
    }

    /**
     * Le script est rejouable (rotation de mot de passe) et réaffirme les
     * attributs de sécurité — un rôle promu SUPERUSER par erreur est corrigé.
     */
    public function test_the_bootstrap_script_is_idempotent_and_reasserts_security(): void
    {
        $this->runBootstrapScript('first-password');

        // Dérive volontaire : quelqu'un a promu le rôle à la main.
        DB::statement('ALTER ROLE '.$this->role.' SUPERUSER BYPASSRLS');

        $this->runBootstrapScript('rotated-password');

        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = ?',
            [$this->role]
        );

        expect((bool) $role->rolsuper)->toBeFalse('la réexécution doit retirer SUPERUSER.');
        expect((bool) $role->rolbypassrls)->toBeFalse('la réexécution doit retirer BYPASSRLS.');
    }

    /**
     * L'effet réel : fail-closed sans tenant, isolation avec tenant.
     */
    public function test_the_created_role_is_actually_filtered_by_rls(): void
    {
        $this->runBootstrapScript('probe-password');
        $this->applyPolicy();

        DB::table(self::TABLE)->insert([
            ['company_id' => self::TENANT_A, 'label' => 'A'],
            ['company_id' => self::TENANT_B, 'label' => 'B'],
        ]);

        // Contrôle : le rôle propriétaire (SUPERUSER) voit les deux lignes.
        expect(DB::table(self::TABLE)->count())->toBe(2, 'contrôle : le propriétaire voit tout.');

        // Le rôle applicatif, sans tenant publié : AUCUNE ligne.
        expect($this->countAsApp(null))->toBe(0, 'sans tenant publié, RLS doit ne rien laisser passer.');

        // Avec le tenant A : une seule ligne.
        expect($this->countAsApp(self::TENANT_A))->toBe(1, 'le tenant A ne doit voir que sa ligne.');
        expect($this->countAsApp(self::TENANT_B))->toBe(1, 'le tenant B ne doit voir que sa ligne.');

        // Écriture hors tenant : refusée par le WITH CHECK.
        $affected = $this->updateAsApp(
            self::TENANT_B,
            'UPDATE shared_tenants.'.self::TABLE." SET label = 'hijacked' WHERE company_id = '".self::TENANT_A."'"
        );
        expect($affected)->toBe(0, 'RLS doit empêcher une écriture hors du tenant courant.');
    }

    /**
     * Exécute le script versionné, exactement comme un opérateur le ferait.
     *
     * On passe par `psql` (et non par une réécriture PHP du SQL) : c'est le
     * seul moyen de prouver que le fichier livré est exécutable. Une
     * réimplémentation en PHP testerait le test, pas la procédure.
     */
    private function runBootstrapScript(string $password): void
    {
        $script = base_path('database/scripts/rls/create-app-role.sql');
        expect(is_file($script))->toBeTrue('le script de bascule doit exister : '.$script);

        $dsn = $this->psqlDsn();

        // Contrat du script : valeur BRUTE, non quotée. Le script applique
        // `%L`, qui quote et échappe lui-même. La version précédente passait
        // une valeur déjà quotée : le mot de passe stocké contenait des quotes
        // littérales et la connexion échouait en `password authentication
        // failed` (mesuré en CI).
        $command = sprintf(
            'psql %s -v ON_ERROR_STOP=1 -v app_role=%s -v app_password=%s -f %s 2>&1',
            escapeshellarg($dsn),
            escapeshellarg($this->role),
            escapeshellarg($password),
            escapeshellarg($script)
        );

        exec($command, $output, $exit);

        expect($exit)->toBe(0, "le script de bascule doit s'exécuter sans erreur.\n".implode("\n", $output));
    }

    /**
     * DSN `psql` dérivé de la connexion Laravel courante.
     */
    private function psqlDsn(): string
    {
        $config = (array) config('database.connections.'.config('database.default'));

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        $dsn = "host={$host} port={$port} dbname={$database} user={$username}";
        if ($password !== '') {
            $dsn .= " password={$password}";
        }

        return $dsn;
    }

    private function createProbeTable(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
        DB::statement('DROP TABLE IF EXISTS shared_tenants.'.self::TABLE);
        DB::statement(
            'CREATE TABLE shared_tenants.'.self::TABLE.' ('
            .'id serial PRIMARY KEY, company_id uuid NOT NULL, label text)'
        );
    }

    private function dropProbeTable(): void
    {
        DB::statement('DROP TABLE IF EXISTS shared_tenants.'.self::TABLE);
    }

    private function applyPolicy(): void
    {
        DB::statement('ALTER TABLE shared_tenants.'.self::TABLE.' ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE shared_tenants.'.self::TABLE.' FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS '.self::TABLE.'_tenant_isolation ON shared_tenants.'.self::TABLE);
        DB::statement(<<<SQL
            CREATE POLICY {$this->policyName()}
            ON shared_tenants.{$this->table()}
            USING (company_id::text = current_setting('app.current_company_id', true))
            WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
            SQL);
    }

    private function table(): string
    {
        return self::TABLE;
    }

    private function policyName(): string
    {
        return self::TABLE.'_tenant_isolation';
    }

    /**
     * Connexion sous le rôle applicatif, tenant éventuellement publié.
     */
    private function appConnection(?string $tenantId): \Illuminate\Database\Connection
    {
        $base = (array) config('database.connections.'.config('database.default'));

        config(['database.connections.rls_app_probe' => array_merge($base, [
            'username' => $this->role,
            'password' => 'probe-password',
        ])]);

        $connection = DB::connection('rls_app_probe');

        if ($tenantId !== null) {
            $connection->statement("SELECT set_config('app.current_company_id', ?, false)", [$tenantId]);
        } else {
            $connection->statement("SELECT set_config('app.current_company_id', '', false)");
        }

        return $connection;
    }

    private function countAsApp(?string $tenantId): int
    {
        $connection = $this->appConnection($tenantId);

        try {
            $row = $connection->selectOne('SELECT count(*) AS c FROM shared_tenants.'.self::TABLE);

            return (int) ($row->c ?? 0);
        } finally {
            DB::purge('rls_app_probe');
        }
    }

    private function updateAsApp(?string $tenantId, string $sql): int
    {
        $connection = $this->appConnection($tenantId);

        try {
            return $connection->update($sql);
        } finally {
            DB::purge('rls_app_probe');
        }
    }

    private function dropRole(): void
    {
        // `DROP OWNED BY` lève 42704 si le rôle n'existe pas — et c'est le cas
        // NORMAL au premier `setUp()` d'un worker frais. Sans cette garde, le
        // test échouait avant même d'avoir testé quoi que ce soit (mesuré en CI :
        // 3 échecs `role "leopardo_app" does not exist`).
        $exists = DB::selectOne('SELECT 1 AS present FROM pg_roles WHERE rolname = ?', [$this->role]);

        if ($exists === null) {
            return;
        }

        DB::statement('DROP OWNED BY '.$this->role.' CASCADE');
        DB::statement('DROP ROLE IF EXISTS '.$this->role);
    }
}
