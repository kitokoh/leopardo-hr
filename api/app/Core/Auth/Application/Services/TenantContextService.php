<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * IAM-L2 (#9092) — sélection du contexte tenant.
 *
 * ## Le constat que ce service corrige
 *
 * `AuthService::findEmployeeInTenantSchemas()` parcourt les schémas tenant et
 * retourne le **premier** employé dont l'e-mail correspond, dans l'ordre de
 * `public.companies`. Une personne membre de deux entreprises obtenait donc un
 * contexte **arbitraire** : c'est l'ordre alphabétique des schémas qui décidait
 * de ses permissions. Aucun endpoint ne permettait de choisir.
 *
 * ## Ce que ce service fait
 *
 * Il énumère les appartenances **réelles** de la personne et ne rend que celles
 * dont l'employé existe encore dans son schéma. La sélection est ensuite
 * **vérifiée** : on ne réémet un jeton que pour une entreprise où
 * l'appartenance est prouvée — jamais pour une entreprise demandée par
 * l'appelant.
 *
 * ## Résolution : `users` d'abord, repli par balayage (question (a) de l'issue)
 *
 * La source de vérité est `public.user_employee_links` (la table de liaison
 * User↔Employee résolue au login). Une personne sans ligne `users` — cas
 * historique — n'est PAS privée de sélection : on retombe sur un balayage des
 * schémas tenant, mais **exhaustif et trié** (jamais « le premier trouvé »).
 * Le repli est donc déterministe, ce que le comportement d'origine n'était pas.
 *
 * ## Ce qu'il ne fait pas
 *
 * Aucune colonne nouvelle, aucune session serveur, aucun nouveau Bounded
 * Context : le contrat réutilise les abilities Sanctum **déjà** posées au login
 * (`tenant_schema:`, `tenant_company:`, `tenant_employee:`).
 */
final class TenantContextService
{
    /**
     * Liste les contextes tenant de la personne identifiée par son e-mail.
     *
     * @return list<array{company_id: string, company_name: string, role: string, manager_role: string|null, permissions: list<string>}>
     */
    public function listContextsForEmail(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            $contexts = $this->contextsFromLinks($user);

            if ($contexts !== []) {
                return $contexts;
            }
        }

        return $this->contextsFromSchemaScan($email);
    }

    /**
     * Vérifie l'appartenance puis rend le contexte correspondant.
     *
     * Rend `null` quand la personne n'appartient PAS à l'entreprise demandée :
     * c'est le point de sécurité du lot — l'appelant traduit ce `null` en 403,
     * jamais en un jeton.
     *
     * @return array{company: Company, employee: Employee, schema: string}|null
     */
    public function resolveContextForEmail(string $email, string $companyId): ?array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            $context = $this->contextFromLinks($user, $companyId);

            if ($context !== null) {
                return $context;
            }
        }

        return $this->contextFromSchemaScan($email, $companyId);
    }

    /**
     * @return list<array{company_id: string, company_name: string, role: string, manager_role: string|null, permissions: list<string>}>
     */
    private function contextsFromLinks(User $user): array
    {
        $links = DB::table('user_employee_links')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->whereNotNull('employee_id')
            ->get();

        $contexts = [];

        foreach ($links as $link) {
            $companyId = is_scalar($link->company_id) ? (string) $link->company_id : null;
            $employeeId = is_scalar($link->employee_id) ? (int) $link->employee_id : null;

            if ($companyId === null || $employeeId === null) {
                continue;
            }

            $company = Company::query()->whereKey($companyId)->first();

            if (! $company instanceof Company) {
                continue;
            }

            $employee = $this->findEmployeeInCompany($company, $employeeId);

            if (! $employee instanceof Employee) {
                continue;
            }

            $contexts[] = $this->describeContext($company, $employee);
        }

        return $this->sortContexts($contexts);
    }

    /**
     * @return array{company: Company, employee: Employee, schema: string}|null
     */
    private function contextFromLinks(User $user, string $companyId): ?array
    {
        $link = DB::table('user_employee_links')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('employee_id')
            ->first();

        if ($link === null) {
            return null;
        }

        $employeeId = is_scalar($link->employee_id) ? (int) $link->employee_id : null;

        if ($employeeId === null) {
            return null;
        }

        $company = Company::query()->whereKey($companyId)->first();

        if (! $company instanceof Company) {
            return null;
        }

        $employee = $this->findEmployeeInCompany($company, $employeeId);

        if (! $employee instanceof Employee) {
            return null;
        }

        return [
            'company' => $company,
            'employee' => $employee,
            'schema' => $this->schemaForCompany($company),
        ];
    }

    /**
     * Repli historique : balayage des schémas tenant, mais EXHAUSTIF et TRIÉ.
     *
     * C'est la différence de fond avec `findEmployeeInTenantSchemas()` : on ne
     * s'arrête pas au premier schéma qui contient l'e-mail, on les collecte
     * tous. Le tri rend le résultat stable d'un appel à l'autre.
     *
     * @return list<array{company_id: string, company_name: string, role: string, manager_role: string|null, permissions: list<string>}>
     */
    private function contextsFromSchemaScan(string $email): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [];
        }

        $schemas = DB::table('public.companies')
            ->whereNotNull('schema_name')
            ->pluck('schema_name')
            ->filter(fn (mixed $schema): bool => is_string($schema) && $this->isSafeSchemaName($schema))
            ->unique()
            ->values();

        $contexts = [];

        foreach ($schemas as $schema) {
            $schema = (string) $schema;

            if (! $this->tenantEmployeesTableExists($schema)) {
                continue;
            }

            $employee = $this->findEmployeeInSchemaByEmail($schema, $email);

            if (! $employee instanceof Employee) {
                continue;
            }

            $company = Company::query()->whereKey($employee->company_id)->first();

            if (! $company instanceof Company) {
                continue;
            }

            $contexts[] = $this->describeContext($company, $employee);
        }

        return $this->sortContexts($contexts);
    }

    /**
     * @return array{company: Company, employee: Employee, schema: string}|null
     */
    private function contextFromSchemaScan(string $email, string $companyId): ?array
    {
        $company = Company::query()->whereKey($companyId)->first();

        if (! $company instanceof Company) {
            return null;
        }

        $schema = $this->schemaForCompany($company);

        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        if (! $this->tenantEmployeesTableExists($schema)) {
            return null;
        }

        $employee = $this->findEmployeeInSchemaByEmail($schema, $email);

        if (! $employee instanceof Employee) {
            return null;
        }

        // L'appartenance est prouvée par la présence de l'employé DANS le
        // schéma de l'entreprise demandée — jamais par la seule demande.
        if ((string) $employee->company_id !== (string) $company->id) {
            return null;
        }

        return [
            'company' => $company,
            'employee' => $employee,
            'schema' => $schema,
        ];
    }

    /**
     * @param  list<array{company_id: string, company_name: string, role: string, manager_role: string|null, permissions: list<string>}>  $contexts
     * @return list<array{company_id: string, company_name: string, role: string, manager_role: string|null, permissions: list<string>}>
     */
    private function sortContexts(array $contexts): array
    {
        usort(
            $contexts,
            static fn (array $a, array $b): int => strcmp($a['company_name'], $b['company_name']),
        );

        return $contexts;
    }

    /**
     * @return array{company_id: string, company_name: string, role: string, manager_role: string|null, permissions: list<string>}
     */
    private function describeContext(Company $company, Employee $employee): array
    {
        return [
            'company_id' => (string) $company->id,
            'company_name' => (string) $company->name,
            'role' => (string) $employee->role,
            'manager_role' => $employee->manager_role !== null ? (string) $employee->manager_role : null,
            'permissions' => $this->permissionsFor($employee),
        ];
    }

    /**
     * Permissions effectives DANS cette entreprise.
     *
     * Miroir exact de `EmployeeResource::capabilities()` — le contrat de
     * session existant — plus les grants de modules délégués. Aucune règle
     * nouvelle : on ne fait que rendre lisible ce que le RBAC applique déjà.
     *
     * @return list<string>
     */
    private function permissionsFor(Employee $employee): array
    {
        $permissions = [];

        if ($employee->isManager()) {
            $permissions[] = 'can_view_dashboard';
            $permissions[] = 'can_view_fleet';
        }

        if ($employee->hasManagerRole('principal', 'rh')) {
            $permissions[] = 'can_create_employees';
            $permissions[] = 'can_manage_invitations';
        }

        if ($employee->hasManagerRole('principal', 'superviseur')) {
            $permissions[] = 'can_manage_biometrics';
        }

        if ($employee->hasManagerRole('principal', 'comptable')) {
            $permissions[] = 'can_view_payroll';
        }

        if ($employee->hasManagerRole('principal')) {
            $permissions[] = 'is_principal';
        }

        foreach ($employee->grantedModuleKeys() as $moduleKey) {
            $permissions[] = 'module:'.$moduleKey;
        }

        return $permissions;
    }

    /**
     * Retrouve l'employé DANS le schéma de SON entreprise.
     *
     * Le `search_path` est posé explicitement puis restauré : sans cela, la
     * requête pourrait résoudre un homonyme d'un autre tenant (c'est
     * exactement le défaut que ce lot corrige).
     */
    private function findEmployeeInCompany(Company $company, int $employeeId): ?Employee
    {
        $schema = $this->schemaForCompany($company);

        if (DB::getDriverName() !== 'pgsql') {
            /** @var Employee|null $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $employeeId)
                ->first();

            return $employee;
        }

        $previous = $this->currentSearchPath();

        try {
            DB::statement('SET search_path TO "'.$schema.'",public');

            /** @var Employee|null $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $employeeId)
                ->first();

            return $employee;
        } finally {
            if ($previous !== null && $previous !== '') {
                DB::statement('SET search_path TO '.$previous);
            }
        }
    }

    private function findEmployeeInSchemaByEmail(string $schema, string $email): ?Employee
    {
        $previous = $this->currentSearchPath();

        try {
            DB::statement('SET search_path TO "'.$schema.'",public');

            /** @var Employee|null $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('email', $email)
                ->first();

            return $employee;
        } finally {
            if ($previous !== null && $previous !== '') {
                DB::statement('SET search_path TO '.$previous);
            }
        }
    }

    private function tenantEmployeesTableExists(string $schema): bool
    {
        $table = DB::selectOne('select to_regclass(?) as table_name', [$schema.'.employees']);

        return $table?->table_name !== null;
    }

    private function schemaForCompany(Company $company): string
    {
        $schema = (string) $company->schema_name;

        if ($schema !== '' && $this->isSafeSchemaName($schema)) {
            return $schema;
        }

        return 'shared_tenants';
    }

    private function isSafeSchemaName(string $schema): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema);
    }

    private function currentSearchPath(): ?string
    {
        $result = DB::selectOne('SHOW search_path');

        if ($result === null) {
            return null;
        }

        $values = (array) $result;

        return isset($values['search_path']) && is_string($values['search_path'])
            ? $values['search_path']
            : null;
    }
}
