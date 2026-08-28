<?php

declare(strict_types=1);

namespace Tests\Support\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CrmTenantFixture — fixtures déterministes du programme CRM (issue #5738).
 *
 * Règles :
 *  - AUCUN secret ni PII réelle : toutes les données sont générées (faker),
 *    réinitialisables (créées dans la transaction du test, `RefreshTenantDatabase`).
 *  - Deux tenants minimum + utilisateurs par rôle (principal / rh / employee).
 *  - Les entités CRM (accounts, contacts, leads, opportunités, activités,
 *    tâches, consentements, événements externes, conversations) sont créées
 *    quand les tables existent (elles arrivent avec le socle V0, #5708/#5709/
 *    #5710/#5722) ; aujourd'hui le seed est un no-op documenté via `report()`.
 *  - Les données de charge/performance vivent dans `Performance/` (séparées
 *    des fixtures fonctionnelles) — voir `Performance/README.md`.
 *
 * Usage dans un test :
 *   [$tenantA, $tenantB] = CrmTenantFixture::createTwoTenants();
 *   $users = CrmTenantFixture::usersByRole($tenantA);
 */
final class CrmTenantFixture
{
    /** Tables CRM attendues (contrat V0/V1). */
    public const CRM_TABLES = [
        'crm_accounts',
        'crm_contacts',
        'crm_leads',
        'crm_pipelines',
        'crm_opportunities',
        'crm_activities',
        'crm_tasks',
        'crm_consents',
        'crm_external_events',
        'crm_conversations',
    ];

    /**
     * Crée deux tenants distincts (shared_tenants) et retourne
     * [$tenantA, $tenantB].
     *
     * @return array{0: Company, 1: Company}
     */
    public static function createTwoTenants(): array
    {
        /** @var Company $tenantA */
        $tenantA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $tenantB */
        $tenantB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        return [$tenantA, $tenantB];
    }

    /**
     * Crée les utilisateurs par rôle dans le tenant donné.
     *
     * @return array{principal: Employee, rh: Employee, employee: Employee}
     */
    public static function usersByRole(Company $company): array
    {
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        return [
            'principal' => $principal,
            'rh' => $rh,
            'employee' => $employee,
        ];
    }

    /**
     * Liste des tables CRM réellement présentes dans le schéma courant.
     *
     * @return list<string>
     */
    public static function availableCrmTables(): array
    {
        return array_values(array_filter(
            self::CRM_TABLES,
            static fn (string $table): bool => Schema::hasTable($table)
        ));
    }

    /**
     * Liste des tables CRM encore absentes du schéma courant.
     *
     * @return list<string>
     */
    public static function missingCrmTables(): array
    {
        return array_values(array_filter(
            self::CRM_TABLES,
            static fn (string $table): bool => ! Schema::hasTable($table)
        ));
    }

    /**
     * Crée une ligne synthétique dans une table CRM si elle existe.
     *
     * Insertion défensive : seule la colonne `company_id` (uuid) est posée,
     * plus `created_at`/`updated_at` si la table les expose. Si la table
     * existe mais impose d'autres colonnes NOT NULL sans défaut, l'insertion
     * échoue bruyamment : c'est le signal que le seed doit être étendu aux
     * colonnes réelles du socle V0 (voir `Performance/README.md`).
     *
     * @return int|null id de la ligne créée, null si table absente.
     */
    public static function insertSyntheticCrmRow(string $table, Company $company): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $columns = Schema::getColumnListing($table);
        $payload = ['company_id' => $company->id];

        if (in_array('created_at', $columns, true) && in_array('updated_at', $columns, true)) {
            $now = now()->toDateTimeString();
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
        }

        return (int) DB::table($table)->insertGetId($payload);
    }

    /**
     * Seed CRM complet pour un tenant : crée une ligne synthétique par table
     * CRM disponible et retourne le rapport d'exécution.
     *
     * @return array{created: list<string>, missing: list<string>}
     */
    public static function seedCrmDataIfAvailable(Company $company): array
    {
        $created = [];
        foreach (self::availableCrmTables() as $table) {
            if (self::insertSyntheticCrmRow($table, $company) !== null) {
                $created[] = $table;
            }
        }

        return [
            'created' => $created,
            'missing' => self::missingCrmTables(),
        ];
    }

    /**
     * Identifiant de corrélation déterministe (convention #1874) — permet de
     * tracer un fixture dans les événements/artefacts sans PII.
     */
    public static function correlationId(): string
    {
        return 'crm-fixture-'.Str::uuid()->toString();
    }
}
