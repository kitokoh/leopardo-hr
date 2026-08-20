<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\AccountLockedException;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\CompanyNotFoundException;
use App\Exceptions\EmployeeNotActiveException;
use App\Exceptions\InvalidCredentialsException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

readonly class AuthService
{
    /**
     * @return array{employee: Employee, token: string, token_type: string, token_expires_at: ?string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;
        $lookup = null;
        if ($this->lookupTableExists()) {
            $lookup = DB::table($this->lookupTable())
                ->where('email', $email)
                ->first();
        }

        /** @var Employee|null $employee */
        $employee = null;
        $employeeSchema = null;

        try {
            if ($lookup) {
                $lookupSchema = is_string($lookup->schema_name ?? null) ? $lookup->schema_name : null;

                if ($lookupSchema && $this->isSafeSchemaName($lookupSchema)) {
                    $this->setTenantSearchPath($lookupSchema);
                    $employeeSchema = $lookupSchema;
                }

                // #2652 : un lookup peut pointer vers un schéma tenant absent ou
                // partiellement migré (ex. démo désactivée en production). Ne jamais
                // requêter une table inexistante → traité comme « aucun employé ».
                if ($employeeSchema === null || $this->tenantEmployeesTableExists($employeeSchema)) {
                    /** @var Employee|null $employee */
                    $employee = Employee::withoutGlobalScopes()
                        ->with('company')
                        ->where('company_id', $lookup->company_id)
                        ->where('id', $lookup->employee_id)
                        ->first();
                }
            }

            if (! $employee) {
                $found = $this->findEmployeeInTenantSchemas($email);
                if ($found !== null) {
                    [$employee, $employeeSchema] = $found;
                    $this->setTenantSearchPath($employeeSchema);
                }
            }

            if (! $employee) {
                // #2652 : le search_path peut être resté pointé sur un schéma
                // fantôme (lookup périmé) — le réinitialiser sur la valeur par
                // défaut AVANT le fallback, sinon la requête lève une
                // QueryException et le login dégrade en 401 au lieu de
                // retrouver l'employé dans le schéma partagé.
                $defaultPath = (string) config('database.connections.pgsql.search_path', 'shared_tenants,public');
                $formattedDefault = $this->formatSearchPath(array_map('trim', explode(',', $defaultPath)));
                // #4495 : ne reposer le chemin par défaut que s'il a réellement
                // divergé — un SET search_path systématique sur le chemin public
                // alimente l'oracle de timing (le test l'interdit explicitement).
                $currentSearchPath = $this->currentSearchPath();
                if ($currentSearchPath === null
                    || preg_replace('/[\s"]+/', '', $currentSearchPath) !== preg_replace('/[\s"]+/', '', $formattedDefault)) {
                    DB::statement('SET search_path TO '.$formattedDefault);
                }

                /** @var Employee|null $employee */
                $employee = Employee::withoutGlobalScopes()
                    ->with('company')
                    ->where('email', $email)
                    ->first();
                $employee?->syncUserLookup();
            }
        } catch (QueryException $e) {
            // #2652 : une résolution d'employé ne doit jamais faire échouer le login
            // en 500 (schéma absent, table partiellement migrée). On journalise en
            // warning structuré et on retombe sur la réponse 401 propre.
            Log::warning('auth.login_employee_resolution_failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
            $employee = null;
            $employeeSchema = null;
        }

        if (! $employee) {
            throw new InvalidCredentialsException;
        }

        try {
            if ($employeeSchema !== null) {
                $this->setTenantSearchPath($employeeSchema);
            }

            // #2973 : getAttributes() renvoie la valeur BRUTE (string) — le
            // instanceof \DateTimeInterface ne se déclenchait jamais depuis
            // #2838 → le verrouillage de compte était silencieusement désactivé.
            // Parse robuste → Carbon (type-safe pour isFuture()/AccountLockedException).
            $lockedRaw = $employee->getAttributes()['locked_until'] ?? null;
            $lockedUntil = null;
            if (is_string($lockedRaw) && $lockedRaw !== '') {
                try {
                    $lockedUntil = \Illuminate\Support\Carbon::parse($lockedRaw);
                } catch (\Throwable) {
                    $lockedUntil = null;
                }
            } elseif ($lockedRaw instanceof \DateTimeInterface) {
                $lockedUntil = \Illuminate\Support\Carbon::instance($lockedRaw);
            }
            if ($this->supportsLoginLocking($employee)
                && $lockedUntil instanceof \Illuminate\Support\Carbon
                && $lockedUntil->isFuture()) {
                throw new AccountLockedException($lockedUntil);
            }

            // QA 2026-08-15 (#2652) : un `password_hash` null/absent ne doit
            // jamais atteindre Hash::check (TypeError → 500 brut). Un compte
            // sans mot de passe exploitable est traité comme identifiants
            // invalides — même traitement que le mot de passe faux.
            if (! is_string($employee->password_hash) || $employee->password_hash === '') {
                throw new InvalidCredentialsException;
            }

            // #2973 : un hash legacy malformé (non-bcrypt) fait lever
            // Hash::check (RuntimeException → 500). Tout échec de vérification
            // est traité comme identifiants invalides (401), jamais un 500.
            try {
                $passwordMatches = Hash::check($password, $employee->password_hash);
            } catch (\Throwable) {
                $passwordMatches = false;
            }

            if (! $passwordMatches) {
                if ($this->supportsLoginLocking($employee)) {
                    $employee->increment('failed_login_attempts');
                    if ($employee->failed_login_attempts >= 5) {
                        $employee->locked_until = now()->addMinutes(15);
                        $employee->save();
                    }
                }
                throw new InvalidCredentialsException;
            }

            // Reset failed attempts on success
            if ($this->supportsLoginLocking($employee) && ($employee->failed_login_attempts > 0 || $employee->locked_until)) {
                $employee->failed_login_attempts = 0;
                $employee->locked_until = null;
                $employee->save();
            }

            $company = $this->resolveCompanyForEmployee($employee);
            if (! $company) {
                throw new CompanyNotFoundException;
            }

            if (in_array($company->status, ['suspended', 'expired'], true)) {
                throw new AccountSuspendedException;
            }

            if ($employee->status !== 'active') {
                throw new EmployeeNotActiveException;
            }

            $employee->forceFill(['last_login_at' => now()])->saveQuietly();

            $tokenName = $deviceName ?: 'api';
            $expirationMinutes = (int) config('sanctum.expiration', 0);
            $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
            $abilities = ['*'];
            if ($employeeSchema !== null) {
                $abilities[] = 'tenant_schema:'.$employeeSchema;
                $abilities[] = 'tenant_email:'.$employee->email;
                $abilities[] = 'tenant_company:'.$company->id;
                $abilities[] = 'tenant_employee:'.$employee->id;
            }

            $tokenResult = $employee->createToken($tokenName, $abilities, $expiresAt);

            return [
                'employee' => $employee,
                'token' => $tokenResult->plainTextToken,
                'token_type' => 'Bearer',
                'token_expires_at' => $expiresAt?->toIso8601String(),
            ];
        } catch (QueryException $e) {
            // Issue #2902 : un compte dont le tenant/schéma est absent ou
            // orphelin (état possible en prod avec un seed partiel) ne doit
            // JAMAIS produire un 500 « Server Error » : les requêtes sur une
            // relation/schéma manquant (SQLSTATE 42P01 / 3F000) ou un accès
            // refusé (42501) sont converties en 401 — même contrat qu'un
            // compte inexistant. Les vraies pannes d'infrastructure (DB
            // injoignable…) continuent de remonter.
            if ($this->isMissingSchemaOrRelation($e)) {
                Log::channel('structured')->warning('auth.login.orphaned_tenant', [
                    'email' => $email,
                    'sqlstate' => $e->getPrevious() instanceof \PDOException
                        ? (string) $e->getPrevious()->getCode()
                        : null,
                    'message' => $e->getMessage(),
                ]);

                throw new InvalidCredentialsException;
            }

            throw $e;
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }
    }

    private function lookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }

    private function lookupTableExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('user_lookups');
        }

        $table = DB::selectOne("select to_regclass('public.user_lookups') as table_name");

        return $table?->table_name !== null;
    }

    private function supportsLoginLocking(Employee $employee): bool
    {
        $connection = $employee->getConnection();
        $table = $employee->getTable();

        return Schema::connection($connection->getName())->hasColumns($table, [
            'failed_login_attempts',
            'locked_until',
        ]);
    }

    /**
     * SSO OIDC (issue #2231/#2197) — émet un token Sanctum pour l'employé
     * identifié par l'IdP, sans mot de passe. Réutilise la résolution
     * cross-schema `public.user_lookups` et les mêmes gardes que login()
     * (statut compte, statut employé, company résolue, abilities tenant).
     *
     * @return array{employee: Employee, token: string, token_type: string, token_expires_at: ?string}
     */
    public function loginViaEmail(string $email, ?string $deviceName = null): array
    {
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;
        $employeeSchema = null;

        try {
            /** @var Employee|null $employee */
            $employee = null;

            if ($this->lookupTableExists()) {
                $lookup = DB::table($this->lookupTable())
                    ->where('email', $email)
                    ->first();

                if ($lookup !== null) {
                    $lookupSchema = is_string($lookup->schema_name ?? null) ? $lookup->schema_name : null;

                    if ($lookupSchema !== null && $this->isSafeSchemaName($lookupSchema)) {
                        $this->setTenantSearchPath($lookupSchema);
                        $employeeSchema = $lookupSchema;
                    }

                    // #2652 : même garde que login() — schéma tenant absent ⇒ « aucun employé ».
                    if ($employeeSchema === null || $this->tenantEmployeesTableExists($employeeSchema)) {
                        /** @var Employee|null $employee */
                        $employee = Employee::withoutGlobalScopes()
                            ->with('company')
                            ->where('company_id', $lookup->company_id)
                            ->where('id', $lookup->employee_id)
                            ->where('email', $email)
                            ->first();
                    }
                }
            }

            if (! $employee) {
                $found = $this->findEmployeeInTenantSchemas($email);
                if ($found !== null) {
                    [$employee, $employeeSchema] = $found;
                    $this->setTenantSearchPath($employeeSchema);
                }
            }
        } catch (QueryException $e) {
            // #2652 : jamais de 500 sur résolution d'employé (schéma absent/migré partiel).
            Log::warning('auth.login_via_email_employee_resolution_failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
            $employee = null;
            $employeeSchema = null;
        }

        if (! $employee) {
            throw new InvalidCredentialsException;
        }

        try {
            if ($employeeSchema !== null) {
                $this->setTenantSearchPath($employeeSchema);
            }

            $company = $this->resolveCompanyForEmployee($employee);
            if (! $company) {
                throw new CompanyNotFoundException;
            }

            if (in_array($company->status, ['suspended', 'expired'], true)) {
                throw new AccountSuspendedException;
            }

            if ($employee->status !== 'active') {
                throw new EmployeeNotActiveException;
            }

            $employee->forceFill(['last_login_at' => now()])->saveQuietly();

            $tokenName = $deviceName ?: 'sso';
            $expirationMinutes = (int) config('sanctum.expiration', 0);
            $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
            $abilities = ['*'];
            if ($employeeSchema !== null) {
                $abilities[] = 'tenant_schema:'.$employeeSchema;
                $abilities[] = 'tenant_email:'.$employee->email;
                $abilities[] = 'tenant_company:'.$company->id;
                $abilities[] = 'tenant_employee:'.$employee->id;
            }

            $tokenResult = $employee->createToken($tokenName, $abilities, $expiresAt);

            return [
                'employee' => $employee,
                'token' => $tokenResult->plainTextToken,
                'token_type' => 'Bearer',
                'token_expires_at' => $expiresAt?->toIso8601String(),
            ];
        } catch (QueryException $e) {
            // Issue #2902 : un compte dont le tenant/schéma est absent ou
            // orphelin (état possible en prod avec un seed partiel) ne doit
            // JAMAIS produire un 500 « Server Error » : les requêtes sur une
            // relation/schéma manquant (SQLSTATE 42P01 / 3F000) ou un accès
            // refusé (42501) sont converties en 401 — même contrat qu'un
            // compte inexistant. Les vraies pannes d'infrastructure (DB
            // injoignable…) continuent de remonter.
            if ($this->isMissingSchemaOrRelation($e)) {
                Log::channel('structured')->warning('auth.login.orphaned_tenant', [
                    'email' => $email,
                    'sqlstate' => $e->getPrevious() instanceof \PDOException
                        ? (string) $e->getPrevious()->getCode()
                        : null,
                    'message' => $e->getMessage(),
                ]);

                throw new InvalidCredentialsException;
            }

            throw $e;
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }
    }

    private function resolveCompanyForEmployee(Employee $employee): ?Company
    {
        $company = $employee->company;
        if ($company instanceof Company) {
            return $company;
        }

        if (DB::getDriverName() !== 'pgsql' || ! $employee->company_id) {
            return null;
        }

        $company = Company::query()
            ->from('public.companies')
            ->whereKey($employee->company_id)
            ->first();

        if ($company instanceof Company) {
            $employee->setRelation('company', $company);
        }

        return $company;
    }

    /**
     * @return array{0: Employee, 1: string}|null
     */
    private function findEmployeeInTenantSchemas(string $email): ?array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $schemas = DB::table('public.companies')
            ->whereNotNull('schema_name')
            ->pluck('schema_name')
            ->filter(fn (mixed $schema): bool => is_string($schema) && $this->isSafeSchemaName($schema))
            ->unique()
            ->values();

        if ($schemas->isEmpty()) {
            return null;
        }

        $previous = $this->currentSearchPath();

        try {
            foreach ($schemas as $schema) {
                if (! $this->tenantEmployeesTableExists((string) $schema)) {
                    continue;
                }

                $this->setTenantSearchPath((string) $schema);

                /** @var Employee|null $employee */
                $employee = Employee::withoutGlobalScopes()
                    ->with('company')
                    ->where('email', $email)
                    ->first();

                if ($employee instanceof Employee) {
                    $employee->syncUserLookup();

                    return [$employee, (string) $schema];
                }
            }
        } finally {
            if ($previous !== null && $previous !== '') {
                DB::statement('SET search_path TO '.$previous);
            }
        }

        return null;
    }

    private function tenantEmployeesTableExists(string $schema): bool
    {
        $table = DB::selectOne('select to_regclass(?) as table_name', [$schema.'.employees']);

        return $table?->table_name !== null;
    }

    private function currentSearchPath(): ?string
    {
        $result = DB::selectOne('SHOW search_path');

        return is_object($result) ? (string) $result->search_path : null;
    }

    private function setTenantSearchPath(string $schema): void
    {
        DB::statement('SET search_path TO '.$this->formatSearchPath([$schema, 'public']));
    }

    /**
     * @param  list<string>  $schemas
     */
    private function formatSearchPath(array $schemas): string
    {
        return implode(',', array_map(fn (string $schema): string => sprintf('"%s"', $schema), $schemas));
    }

    private function isSafeSchemaName(string $schema): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema);
    }

    /**
     * Détecte les erreurs de requêtes sur des relations/schémas manquants
     * (compte orphelin, migration partielle) vs une vraie panne DB.
     */
    private function isMissingSchemaOrRelation(QueryException $e): bool
    {
        if (! $e->getPrevious() instanceof \PDOException) {
            return false;
        }

        return in_array((string) $e->getPrevious()->getCode(), [
            '42P01', // undefined_table (relation « x » does not exist)
            '3F000', // invalid_schema_name (schema « x » does not exist)
            '42501', // insufficient_privilege
        ], true);
    }
}
