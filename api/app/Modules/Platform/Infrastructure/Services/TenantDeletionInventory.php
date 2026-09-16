<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7475 — Inventaire chiffré d'un tenant avant suppression.
 *
 * « Une suppression sèche détruirait des données de paie sans retour
 * possible » (issue #7475). L'opérateur doit donc voir **ce qu'il détruit**
 * avant de valider : ce service ne supprime rien, il compte.
 *
 * Deux responsabilités :
 *  1. `for()` — l'inventaire montré à l'opérateur (compteurs lisibles +
 *     décision `mode` imposée par la présence de paie) ;
 *  2. `tenantTables()` — la découverte des tables du schéma `shared_tenants`
 *     portant `company_id`, qui sert de base à la purge **et** à sa
 *     vérification de complétude (aucune table oubliée).
 *
 * La découverte est **dynamique** (information_schema) et non une liste en
 * dur : `shared_tenants` reçoit ~390 migrations, une liste figée serait fausse
 * au prochain module ajouté — et une table oubliée laisserait des données
 * personnelles en base après une purge RGPD.
 */
final class TenantDeletionInventory
{
    /** Schéma physique de tous les tenants (mode « shared », cf. Company::booted()). */
    public const TENANT_SCHEMA = 'shared_tenants';

    /**
     * Tables tenant **retenues** en mode `anonymize` : la conservation des
     * pièces de paie est une obligation légale (le reste est effacé).
     *
     * @var list<string>
     */
    public const PAYROLL_RETENTION_TABLES = [
        'employees',
        'payroll_runs',
        'pay_slips',
    ];

    /**
     * Compteurs lisibles affichés à l'opérateur avant validation.
     *
     * « X employés, Y bulletins, Z documents seront détruits » plutôt qu'un
     * message générique (critère d'acceptation 3 de #7475).
     *
     * @var array<string, array{table: string, filter: string|null}>
     */
    private const COUNTERS = [
        'employees' => ['table' => 'employees', 'filter' => null],
        'employees_active' => ['table' => 'employees', 'filter' => 'active'],
        'payroll_runs' => ['table' => 'payroll_runs', 'filter' => null],
        'pay_slips' => ['table' => 'pay_slips', 'filter' => null],
        'employee_documents' => ['table' => 'employee_documents', 'filter' => null],
        'attendance_logs' => ['table' => 'attendance_logs', 'filter' => null],
        'onboarding_steps' => ['table' => 'onboarding_steps', 'filter' => null],
    ];

    /**
     * Inventaire complet d'un tenant.
     *
     * @return array{
     *     company_id: string,
     *     company_name: string,
     *     status: string,
     *     deletable: bool,
     *     blocked_by: list<string>,
     *     has_payroll_data: bool,
     *     required_mode: string|null,
     *     counters: array<string, int>,
     *     tenant_table_count: int,
     *     platform_rows: array<string, int>
     * }
     */
    public function for(Company $company): array
    {
        $counters = $this->counters($company->id);
        $hasPayroll = ($counters['payroll_runs'] ?? 0) > 0 || ($counters['pay_slips'] ?? 0) > 0;

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'status' => (string) $company->status,
            'deletable' => $this->isDeletable($company),
            'blocked_by' => $this->blockers($company),
            'has_payroll_data' => $hasPayroll,
            // Critère d'acceptation 5 : avec de la paie, aucun mode n'est
            // « par défaut » — l'opérateur doit choisir explicitement.
            'required_mode' => $hasPayroll ? null : TenantDeletionService::MODE_PURGE,
            'counters' => $counters,
            'tenant_table_count' => count($this->tenantTables()),
            'platform_rows' => $this->platformRowCounts($company->id),
        ];
    }

    /**
     * Nom qualifié d'une table tenant.
     *
     * Indispensable : `PlatformCompanyLookup::findOrFail()` (appelé en amont par
     * la console plateforme) pose `SET search_path TO public`. Sans
     * qualification, `DB::table('employees')` ne trouverait plus la table du
     * schéma tenant — et une purge « réussie » ne supprimerait rien.
     */
    public static function qualified(string $table): string
    {
        return self::TENANT_SCHEMA.'.'.$table;
    }

    /**
     * La colonne existe-t-elle sur la table tenant (indépendamment du
     * `search_path` courant) ?
     */
    public static function tenantColumnExists(string $table, string $column): bool
    {
        return self::tenantColumnProfile($table, $column) !== null;
    }

    /**
     * Profil d'une colonne tenant : existence, nullabilité, type.
     *
     * L'anonymisation en a besoin : toutes les colonnes d'identité ne sont pas
     * nullables (`employees.metadata` est `jsonb NOT NULL` — constaté au test),
     * et écrire `null` dans une colonne non nullable fait échouer la purge.
     *
     * @return array{nullable: bool, type: string}|null
     */
    public static function tenantColumnProfile(string $table, string $column): ?array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $row = DB::selectOne(
            'SELECT is_nullable, data_type FROM information_schema.columns
              WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [self::TENANT_SCHEMA, $table, $column]
        );

        if ($row === null) {
            return null;
        }

        /** @var object{is_nullable: string, data_type: string} $row */
        return [
            'nullable' => strtoupper((string) $row->is_nullable) === 'YES',
            'type' => (string) $row->data_type,
        ];
    }

    /**
     * Nombre total de lignes du tenant dans le schéma `shared_tenants`.
     *
     * Sert de garde de complétude après purge : si ce total n'est pas nul,
     * la purge est incomplète et doit être annulée.
     */
    public function tenantRowCount(string $companyId): int
    {
        $total = 0;

        foreach ($this->tenantTables() as $table) {
            $total += DB::table(self::qualified($table))->where('company_id', $companyId)->count();
        }

        return $total;
    }

    /**
     * Toutes les tables du schéma tenant portant `company_id`.
     *
     * @return list<string>
     */
    public function tenantTables(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [];
        }

        /** @var list<object{table_name: string}> $rows */
        $rows = DB::select(
            'SELECT c.table_name
                   FROM information_schema.columns c
                   JOIN information_schema.tables t
                     ON t.table_schema = c.table_schema
                    AND t.table_name = c.table_name
                  WHERE c.table_schema = ?
                    AND c.column_name = ?
                    AND t.table_type = ?
                  ORDER BY c.table_name',
            [self::TENANT_SCHEMA, 'company_id', 'BASE TABLE']
        );

        /** @var list<string> $tables */
        $tables = array_map(
            static fn (object $row): string => (string) $row->table_name,
            $rows
        );

        return $tables;
    }

    /**
     * Nombre de lignes du tenant côté plateforme (`public`).
     *
     * @return array<string, int>
     */
    public function platformRowCounts(string $companyId): array
    {
        $counts = [];

        foreach (TenantDeletionService::PUBLIC_TENANT_TABLES as $table) {
            if (! $this->publicTableExists($table)) {
                continue;
            }

            $counts[$table] = DB::table($table)->where('company_id', $companyId)->count();
        }

        return $counts;
    }

    /**
     * Un tenant n'est supprimable qu'**après désactivation** (critère 1).
     */
    public function isDeletable(Company $company): bool
    {
        return in_array((string) $company->status, TenantDeletionService::DELETABLE_STATUSES, true);
    }

    /**
     * Motifs de refus lisibles, dans l'ordre des critères d'acceptation.
     *
     * @return list<string>
     */
    public function blockers(Company $company): array
    {
        $blockers = [];

        if (! $this->isDeletable($company)) {
            $blockers[] = 'TENANT_NOT_DEACTIVATED';
        }

        return $blockers;
    }

    /**
     * @return array<string, int>
     */
    private function counters(string $companyId): array
    {
        $counters = [];

        foreach (self::COUNTERS as $key => $definition) {
            $table = $definition['table'];

            if (! $this->tenantTableExists($table)) {
                $counters[$key] = 0;

                continue;
            }

            $query = DB::table(self::qualified($table))->where('company_id', $companyId);

            if ($definition['filter'] !== null) {
                $query->where('status', $definition['filter']);
            }

            $counters[$key] = $query->count();
        }

        return $counters;
    }

    private function tenantTableExists(string $table): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [self::TENANT_SCHEMA, $table]
        ) !== null;
    }

    private function publicTableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
