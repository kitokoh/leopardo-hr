<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * PA2-COMM-007 - Resolves an `Employee` by email across tenant schemas
 * using the platform's O(1) `public.user_lookups` dispatch table, the same
 * mechanism `AuthService::login()` relies on. Used by
 * `EmailBounceWebhookController` to locate the recipient of a bounced
 * email without needing the tenant context that normal authenticated
 * requests carry.
 */
class EmployeeEmailLookupService
{
    /**
     * Resolves the employee for `$email` and switches the active tenant
     * search path to their schema before returning, so callers can update
     * the employee directly. Returns null (and leaves the search path
     * untouched) when no match exists.
     */
    public function resolve(string $email): ?Employee
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->lookupTableExists()) {
            return null;
        }

        $lookup = DB::table('public.user_lookups')->where('email', $email)->first();

        if (! $lookup) {
            return null;
        }

        $schema = is_string($lookup->schema_name ?? null) ? $lookup->schema_name : null;

        if (! is_string($schema) || $schema === '' || ! $this->isSafeSchemaName($schema)) {
            return null;
        }

        DB::statement('SET search_path TO '.$this->formatSearchPath([$schema, 'public']));

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()
            ->where('id', $lookup->employee_id)
            ->where('company_id', $lookup->company_id)
            ->where('email', $email)
            ->first();

        return $employee;
    }

    private function lookupTableExists(): bool
    {
        $table = DB::selectOne("select to_regclass('public.user_lookups') as table_name");

        return $table?->table_name !== null;
    }

    private function isSafeSchemaName(string $schema): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema);
    }

    /**
     * @param  list<string>  $schemas
     */
    private function formatSearchPath(array $schemas): string
    {
        return implode(',', array_map(fn (string $schema): string => sprintf('"%s"', $schema), $schemas));
    }
}
