<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * #7475 — inventaire, purge et anonymisation d'un tenant (super-admin).
 *
 * Une société porte un schéma tenant (dédié ou `shared_tenants`), des employés,
 * des contrats, de la paie, des pointages, des documents, plus des lignes
 * plateforme (abonnement, factures, audit). Une suppression sèche détruirait des
 * données de paie sans retour : d'où un parcours **en deux temps** (désactivation
 * puis purge explicite) et un **inventaire chiffré** avant validation.
 *
 * Deux invariants tiennent ce service :
 *
 * 1. **Aucune table n'est codée en dur.** Les tables à purger sont découvertes
 *    dans `information_schema` (celles qui portent `company_id` dans le schéma du
 *    tenant, puis dans le schéma `public`). Une table ajoutée demain est donc
 *    purgée sans modification, et une table absente n'est pas une erreur.
 * 2. **Les suppressions sont isolées par des SAVEPOINT.** En PostgreSQL, un
 *    `DELETE` refusé par une contrainte de clé étrangère **avorte la
 *    transaction entière** (`25P02`) : le `try/catch` ne suffit pas, l'erreur
 *    empoisonne tout ce qui suit. On isole donc chaque table, on réessaie en
 *    passes successives (les dépendantes partent après leurs parents), et on
 *    échoue explicitement si des lignes subsistent.
 */
final class TenantPurgeService
{
    /** Schéma partagé par défaut (un tenant « shared » n'a pas de schéma dédié). */
    public const SHARED_SCHEMA = 'shared_tenants';

    /** Nombre de passes de suppression (graphe de dépendances FK). */
    private const MAX_DELETE_PASSES = 8;

    /**
     * Tables plateforme à NE PAS purger : le journal des purges porte lui-même
     * un `company_id` (c'est sa clé de lecture), et il est précisément la trace
     * qui doit survivre à l'opération (critère 4 de #7475). La découvrir par
     * `information_schema` puis la supprimer effacerait la preuve de la purge.
     */
    private const PRESERVED_TABLES = ['platform_company_purges'];

    /**
     * Schéma réellement porteur des données du tenant.
     */
    public function schemaFor(Company $company): string
    {
        $schema = (string) ($company->schema_name ?? '');

        if ($company->tenancy_type === 'schema' && $schema !== '' && $schema !== self::SHARED_SCHEMA) {
            return $schema;
        }

        return self::SHARED_SCHEMA;
    }

    /**
     * @return list<string>
     */
    public function tablesWithCompanyId(string $schema): array
    {
        $rows = DB::select(
            'select c.table_name as table_name
               from information_schema.columns c
               join information_schema.tables t
                 on t.table_schema = c.table_schema and t.table_name = c.table_name
              where c.table_schema = ? and c.column_name = ? and t.table_type = ?
              order by c.table_name',
            [$schema, 'company_id', 'BASE TABLE']
        );

        $tables = [];
        foreach ($rows as $row) {
            if (property_exists($row, 'table_name') && is_string($row->table_name)) {
                $tables[] = $row->table_name;
            }
        }

        return $tables;
    }

    /**
     * Inventaire chiffré : ce que la purge détruirait, table par table.
     *
     * @return array{
     *     schema: string,
     *     resources: array<string, int>,
     *     total_rows: int,
     *     payroll_rows: int,
     *     payroll_tables: list<string>,
     *     requires_explicit_mode: bool
     * }
     */
    public function inventory(Company $company): array
    {
        $schema = $this->schemaFor($company);
        $companyId = (string) $company->id;

        $resources = [];
        foreach ($this->purgeableTables($this->tablesWithCompanyId($schema)) as $table) {
            $resources[$table] = (int) DB::table($schema.'.'.$table)->where('company_id', $companyId)->count();
        }

        $payrollTables = [];
        $payrollRows = 0;
        foreach ($resources as $table => $count) {
            if ($count > 0 && self::isPayrollTable($table)) {
                $payrollTables[] = $table;
                $payrollRows += $count;
            }
        }

        return [
            'schema' => $schema,
            'resources' => $resources,
            'total_rows' => array_sum($resources),
            'payroll_rows' => $payrollRows,
            'payroll_tables' => $payrollTables,
            // Critère 5 : une société avec des données de paie (obligation de
            // conservation) ne part pas sans choix explicite de l'opérateur.
            'requires_explicit_mode' => $payrollRows > 0,
        ];
    }

    /**
     * Purge : destruction complète des données du tenant.
     *
     * @return array<string, int> volumes détruits
     */
    public function purge(Company $company): array
    {
        $schema = $this->schemaFor($company);
        $companyId = (string) $company->id;

        $volumes = [];

        DB::transaction(function () use ($schema, $companyId, &$volumes, $company): void {
            if ($schema !== self::SHARED_SCHEMA && self::isSafeSchemaName($schema)) {
                // Tenant à schéma dédié : le schéma EST la donnée du tenant.
                $before = $this->countSchemaRows($schema, $companyId);
                DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
                $volumes = $before;
            } else {
                $volumes = $this->deleteCompanyRows($schema, $this->purgeableTables($this->tablesWithCompanyId($schema)), $companyId);
            }

            // Lignes plateforme (schéma public) : utilisateurs rattachés, demandes
            // d'inscription, support, SSO… puis la société elle-même.
            $public = $this->deleteCompanyRows('public', $this->purgeableTables($this->tablesWithCompanyId('public')), $companyId);
            foreach ($public as $table => $count) {
                $key = 'public.'.$table;
                $volumes[$key] = ($volumes[$key] ?? 0) + $count;
            }

            DB::statement('SET search_path TO public');
            $removed = DB::table('public.companies')->where('id', $companyId)->delete();
            $volumes['public.companies'] = $removed;

            if ($removed !== 1) {
                throw new RuntimeException(
                    'Purge interrompue : la société '.$companyId.' ('.$company->name.') n\'a pas pu être supprimée de public.companies.'
                );
            }
        });

        return $volumes;
    }

    /**
     * Anonymisation : les données de paie sont conservées (obligation de
     * conservation), les données identifiantes sont effacées.
     *
     * @return array<string, int> volumes anonymisés (lignes touchées par table)
     */
    public function anonymise(Company $company): array
    {
        $schema = $this->schemaFor($company);
        $companyId = (string) $company->id;
        $suffix = substr($companyId, 0, 8);

        $touched = [];

        DB::transaction(function () use ($schema, $companyId, $suffix, &$touched): void {
            if ($this->hasTable($schema, 'employees')) {
                $touched['employees'] = (int) DB::table($schema.'.employees')
                    ->where('company_id', $companyId)
                    ->update($this->employeeAnonymisation($schema));
            }

            if (
                $this->hasTable('public', 'users')
                && $this->hasColumn('public', 'users', 'email')
                && $this->hasColumn('public', 'users', 'company_id')
            ) {
                // L'e-mail est unique : on le dérive de la ligne, sinon deux
                // utilisateurs anonymisés se heurteraient à la même valeur.
                $touched['public.users'] = (int) DB::table('public.users')
                    ->where('company_id', $companyId)
                    ->update(['email' => DB::raw("'anon+' || id || '@purged.invalid'")]);
            }

            DB::statement('SET search_path TO public');
            $touched['public.companies'] = (int) DB::table('public.companies')
                ->where('id', $companyId)
                ->update([
                    'name' => 'Société anonymisée ('.$suffix.')',
                    'email' => 'anon+'.$suffix.'@purged.invalid',
                    'phone' => null,
                    'address' => null,
                    'city' => '—',
                    'notes' => null,
                ]);
        });

        return $touched;
    }

    /**
     * Colonnes identifiantes d'`employees` effectivement présentes, remplacées
     * par des valeurs neutres. La liste est explicite (pas de devinette) et
     * chaque colonne est vérifiée avant l'écriture.
     *
     * @return array<string, mixed>
     */
    private function employeeAnonymisation(string $schema): array
    {
        $replacements = [
            'first_name' => 'Anonymisé',
            'last_name' => 'Anonymisé',
            // E-mail unique : dérivé de la ligne (cf. `public.users`).
            'email' => DB::raw("'anon+' || id || '@purged.invalid'"),
            'phone' => null,
            'iban' => null,
            'bank_account' => null,
            'national_id' => null,
            'photo_path' => null,
            'zkteco_id' => null,
        ];

        $data = [];
        foreach ($replacements as $column => $value) {
            if ($this->hasColumn($schema, 'employees', $column)) {
                $data[$column] = $value;
            }
        }

        return $data;
    }

    /**
     * Supprime les lignes `company_id = $companyId` de chaque table, en passes
     * successives : une table dont une dépendante existe encore est réessayée
     * après. Chaque `DELETE` est isolé par un SAVEPOINT — en PostgreSQL, un
     * `DELETE` refusé avorte la transaction entière (`25P02`) et un `try/catch`
     * seul ne protège rien.
     *
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function deleteCompanyRows(string $schema, array $tables, string $companyId): array
    {
        $deleted = array_fill_keys($tables, 0);
        $remaining = $tables;

        for ($pass = 0; $pass < self::MAX_DELETE_PASSES && $remaining !== []; $pass++) {
            $blocked = [];
            $progress = false;

            foreach ($remaining as $index => $table) {
                DB::statement('SAVEPOINT purge_row_'.$index);
                try {
                    $count = (int) DB::table($schema.'.'.$table)->where('company_id', $companyId)->delete();
                    DB::statement('RELEASE SAVEPOINT purge_row_'.$index);
                    if ($count > 0) {
                        $progress = true;
                        $deleted[$table] += $count;
                    }
                } catch (QueryException) {
                    DB::statement('ROLLBACK TO SAVEPOINT purge_row_'.$index);
                    $blocked[] = $table;
                }
            }

            if (! $progress) {
                $remaining = $blocked;
                break;
            }

            $remaining = $blocked;
        }

        if ($remaining !== []) {
            throw new RuntimeException(
                'Purge incomplète : des lignes subsistent dans '.implode(', ', $remaining)
                .' (dépendances de clés étrangères hors du périmètre de la société).'
            );
        }

        return array_filter($deleted, static fn (int $count): bool => $count > 0);
    }

    /**
     * @return array<string, int>
     */
    private function countSchemaRows(string $schema, string $companyId): array
    {
        $counts = [];
        foreach ($this->tablesWithCompanyId($schema) as $table) {
            $count = (int) DB::table($schema.'.'.$table)->where('company_id', $companyId)->count();
            if ($count > 0) {
                $counts[$table] = $count;
            }
        }

        return $counts;
    }

    /**
     * Retire de la liste les tables qui ne sont PAS des données du tenant.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function purgeableTables(array $tables): array
    {
        return array_values(array_filter(
            $tables,
            static fn (string $table): bool => ! in_array($table, self::PRESERVED_TABLES, true)
        ));
    }

    private function hasTable(string $schema, string $table): bool
    {
        $row = DB::selectOne(
            'select 1 as found from information_schema.tables where table_schema = ? and table_name = ? and table_type = ?',
            [$schema, $table, 'BASE TABLE']
        );

        return $row !== null;
    }

    private function hasColumn(string $schema, string $table, string $column): bool
    {
        $row = DB::selectOne(
            'select 1 as found from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
            [$schema, $table, $column]
        );

        return $row !== null;
    }

    /**
     * Tables dont le contenu relève d'une obligation de conservation (paie).
     */
    private static function isPayrollTable(string $table): bool
    {
        foreach (['pay_slip', 'payslip', 'payroll', 'salary', 'contract', 'invoice', 'accounting'] as $needle) {
            if (str_contains($table, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Garde-fou : on ne construit un `DROP SCHEMA` que sur un identifiant de
     * schéma strictement alphanumérique (les migrations appliquent la même
     * contrainte via `Company::getSafeSearchPath()`).
     */
    private static function isSafeSchemaName(string $schema): bool
    {
        return preg_match('/^[a-z0-9_]+$/', $schema) === 1
            && ! in_array($schema, ['public', 'information_schema', 'pg_catalog'], true);
    }
}
