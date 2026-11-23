<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9854 — preuve que les VRAIES migrations sont rejouees, pas seulement une
 * fixture ecrite a la main.
 *
 * ## Le probleme que ce test ferme
 *
 * L'audit a constate que 260 fichiers de test utilisent `CreatesMvpSchema`,
 * qui construit un schema fixture a la main (~250 tables, DDL manuel, 8 547
 * lignes). Un bug de migration — colonne manquante, contrainte absente,
 * mauvais schema — ne ferait alors echouer AUCUN test : c'est exactement le
 * type de bug deja rencontre (deux tables `invoices` divergentes, `public`
 * vs `shared_tenants`).
 *
 * ## Ce que ce test verifie
 *
 * Il utilise `RefreshTenantDatabase`, qui execute reellement
 * `migrate:fresh --path=database/migrations/public` puis
 * `migrate --path=database/migrations/tenant` (voir
 * `tests/RefreshTenantDatabase.php`). Il assert ensuite que :
 *
 *  1. le depot de migrations `shared_tenants.migrations` contient autant de
 *     lignes que de fichiers de migration tenant sur le disque — donc les
 *     migrations ont bien TOUTES ete jouees, pas un sous-ensemble ;
 *  2. les tables tenant canoniques atterrissent dans `shared_tenants` et les
 *     tables publiques dans `public` (placement de schema correct) ;
 *  3. chaque migration tenant declaree sur le disque est enregistree comme
 *     jouee (aucune migration silencieusement ignoree).
 *
 * Le point 3 est le garde-fou central : il transforme « la suite est verte »
 * en « la suite est verte ET le schema vient des migrations ».
 */
final class RealMigrationsReplayTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Toutes les migrations tenant du disque sont-elles enregistrees comme
     * jouees ? C'est la preuve directe que le schema vient des migrations.
     */
    public function test_every_tenant_migration_on_disk_is_recorded_as_run(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Verification de schema PostgreSQL uniquement.');
        }

        $onDisk = $this->migrationNamesOnDisk('database/migrations/tenant');

        self::assertNotEmpty($onDisk, 'Aucune migration tenant trouvee sur le disque.');

        $recorded = DB::table('shared_tenants.migrations')
            ->pluck('migration')
            ->map(static fn ($name): string => (string) $name)
            ->all();

        $missing = array_values(array_diff($onDisk, $recorded));

        self::assertSame(
            [],
            $missing,
            "Migrations tenant presentes sur le disque mais NON jouees :\n - "
            .implode("\n - ", $missing)
            ."\nLe schema de test ne vient donc pas des migrations reelles."
        );
    }

    /**
     * Le nombre de migrations jouees correspond-il au disque ? Detecte une
     * migration ajoutee mais jamais executee (fichier orphelin, chemin
     * exclu, `--path` incomplet).
     */
    public function test_tenant_migration_count_matches_disk(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Verification de schema PostgreSQL uniquement.');
        }

        $onDisk = count($this->migrationNamesOnDisk('database/migrations/tenant'));
        $recorded = DB::table('shared_tenants.migrations')->count();

        self::assertSame(
            $onDisk,
            $recorded,
            "Le depot de migrations tenant contient {$recorded} lignes pour {$onDisk} fichiers sur le disque."
        );
    }

    /**
     * Les tables tenant atterrissent dans `shared_tenants`, les tables
     * publiques dans `public`. Un mauvais `search_path` au moment du
     * `migrate` placerait les tables dans le mauvais schema — c'est le bug
     * `invoices` deja observe en production.
     */
    public function test_migrations_land_in_the_expected_schema(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Verification de schema PostgreSQL uniquement.');
        }

        // Table tenant canonique (database/migrations/tenant) -> shared_tenants.
        $tenantSchema = DB::scalar(
            "SELECT table_schema FROM information_schema.tables WHERE table_name = 'employees'"
        );
        self::assertSame('shared_tenants', $tenantSchema, 'employees doit vivre dans shared_tenants.');

        // Table publique canonique (database/migrations/public) -> public.
        $publicSchema = DB::scalar(
            "SELECT table_schema FROM information_schema.tables WHERE table_name = 'companies'"
        );
        self::assertSame('public', $publicSchema, 'companies doit vivre dans public.');
    }

    /**
     * Le schema produit par les migrations doit contenir les colonnes que
     * l'application attend sur une table sensible. Une migration qui n'aurait
     * pas tourne laisserait la colonne absente sans qu'aucun test ne le voie.
     */
    public function test_sensitive_table_has_columns_from_migrations(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Verification de schema PostgreSQL uniquement.');
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'shared_tenants')
            ->where('table_name', 'employees')
            ->pluck('column_name')
            ->map(static fn ($c): string => (string) $c)
            ->all();

        self::assertNotEmpty($columns, 'La table employees est absente du schema migre.');

        // Colonnes structurantes attendues sur employees (identite + tenant).
        foreach (['id', 'company_id', 'email'] as $expected) {
            self::assertContains(
                $expected,
                $columns,
                "La colonne employees.{$expected} manque : la migration correspondante n'a pas tourne."
            );
        }
    }

    /**
     * Noms de migration (sans extension) d'un repertoire, tries.
     *
     * @return list<string>
     */
    private function migrationNamesOnDisk(string $relativePath): array
    {
        $files = glob(base_path($relativePath).'/*.php') ?: [];

        $names = array_map(
            static fn (string $path): string => basename($path, '.php'),
            $files
        );

        sort($names);

        return array_values($names);
    }
}
