<?php

namespace App\Services;

use App\Exceptions\AccountLockedException;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\CompanyNotFoundException;
use App\Exceptions\EmployeeNotActiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
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

                $employee = Employee::withoutGlobalScopes()
                    ->with('company')
                    ->where('company_id', $lookup->company_id)
                    ->where('id', $lookup->employee_id)
                    ->first();
            }

            if (! $employee) {
                $found = $this->findEmployeeInTenantSchemas($email);
                if ($found !== null) {
                    [$employee, $employeeSchema] = $found;
                    $this->setTenantSearchPath($employeeSchema);
                }
            }

            if (! $employee) {
                $employee = Employee::withoutGlobalScopes()
                    ->with('company')
                    ->where('email', $email)
                    ->first();
                $employee?->syncUserLookup();
            }

            if (! $employee) {
                throw new InvalidCredentialsException;
            }

            if ($employeeSchema !== null) {
                $this->setTenantSearchPath($employeeSchema);
            }

            if ($this->supportsLoginLocking($employee) && $employee->locked_until && $employee->locked_until->isFuture()) {
                throw new AccountLockedException($employee->locked_until);
            }

            if (! Hash::check($password, $employee->password_hash)) {
                if ($this->supportsLoginLocking($employee)) {
                    $employee->increment('failed_login_attempts');
                    if ($employee->failed_login_attempts >= 5) {
                        $employee->update(['locked_until' => now()->addMinutes(15)]);
                    }
                }
                throw new InvalidCredentialsException;
            }

            // Reset failed attempts on success
            if ($this->supportsLoginLocking($employee) && ($employee->failed_login_attempts > 0 || $employee->locked_until)) {
                $employee->update([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);
            }

            $company = $employee->company;
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
}
