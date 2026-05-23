<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();

        if (! $employee) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['error' => 'UNAUTHENTICATED'], 401);
            }

            /** @var RedirectResponse $response */
            $response = redirect()->route('login');

            return $response;
        }

        if (! $employee instanceof Employee) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['error' => 'COMPANY_NOT_FOUND'], 403);
            }

            abort(403);
        }

        $company = $employee->company;

        if (! $company) {
            $employee = $this->hydrateTenantEmployee($employee, $request) ?? $employee;
            $company = $employee->company;
        }

        if (! $company) {
            if ($employee->role === 'ordinary') {
                return $next($request);
            }
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['error' => 'COMPANY_NOT_FOUND'], 403);
            }

            abort(403);
        }

        if (in_array($company->status, ['suspended', 'expired'], true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['error' => 'ACCOUNT_SUSPENDED'], 403);
            }

            abort(403);
        }

        if ($employee->status === 'archived') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['error' => 'EMPLOYEE_ARCHIVED'], 403);
            }

            abort(403);
        }

        if ($employee->status === 'suspended') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['error' => 'EMPLOYEE_SUSPENDED'], 403);
            }

            abort(403);
        }

        $request->attributes->set('company', $company);
        $this->tenantManager->setTenant($company);

        if (class_exists('\Sentry\State\HubAdapter') || class_exists('\Sentry\Laravel\Facade')) {
            \Sentry\configureScope(function (Scope $scope) use ($company) {
                $scope->setTag('company_id', $company->id);
                $scope->setTag('company_slug', $company->slug);
            });
        }

        try {
            return $next($request);
        } finally {
            $this->tenantManager->resetToPrevious();
        }
    }

    private function hydrateTenantEmployee(Employee $employee, Request $request): ?Employee
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $tokenContext = $this->tenantContextFromToken($employee);
        if ($tokenContext !== null) {
            $tenantEmployee = $this->findTenantEmployee(
                schema: $tokenContext['schema'],
                employeeId: $tokenContext['employee_id'],
                companyId: $tokenContext['company_id'],
                email: $tokenContext['email'],
            );

            if ($tenantEmployee instanceof Employee) {
                return $this->replaceRequestUser($employee, $tenantEmployee, $request);
            }
        }

        if (! $employee->email) {
            return null;
        }

        if (DB::scalar("select to_regclass('public.user_lookups')") === null) {
            return null;
        }

        /** @var object{schema_name: mixed, employee_id: mixed, company_id: mixed}|null $lookup */
        $lookup = DB::table('public.user_lookups')
            ->where('email', $employee->email)
            ->first();

        if (! $lookup) {
            return null;
        }

        $schema = is_string($lookup->schema_name) ? $lookup->schema_name : null;
        if (
            ! $schema
            || ! $this->isSafeSchemaName($schema)
            || ! is_scalar($lookup->employee_id)
            || ! is_scalar($lookup->company_id)
        ) {
            return null;
        }

        $tenantEmployee = $this->findTenantEmployee(
            schema: $schema,
            employeeId: (int) $lookup->employee_id,
            companyId: (string) $lookup->company_id,
            email: $employee->email,
        );

        if (! $tenantEmployee instanceof Employee) {
            return null;
        }

        return $this->replaceRequestUser($employee, $tenantEmployee, $request);
    }

    /**
     * @return array{schema: string, email: string, company_id: string, employee_id: int}|null
     */
    private function tenantContextFromToken(Employee $employee): ?array
    {
        $token = $employee->currentAccessToken();
        $rawAbilities = rescue(static fn (): mixed => $token->abilities, [], false);
        $rawAbilities = is_array($rawAbilities) ? $rawAbilities : [];
        $abilities = array_values(array_filter($rawAbilities, static fn (mixed $ability): bool => is_string($ability)));

        $schema = $this->abilityValue($abilities, 'tenant_schema:');
        $email = $this->abilityValue($abilities, 'tenant_email:');
        $companyId = $this->abilityValue($abilities, 'tenant_company:');
        $employeeId = $this->abilityValue($abilities, 'tenant_employee:');

        if (! $schema || ! $email || ! $companyId || ! $employeeId || ! $this->isSafeSchemaName($schema)) {
            return null;
        }

        return [
            'schema' => $schema,
            'email' => $email,
            'company_id' => $companyId,
            'employee_id' => (int) $employeeId,
        ];
    }

    /**
     * @param  list<string>  $abilities
     */
    private function abilityValue(array $abilities, string $prefix): ?string
    {
        foreach ($abilities as $ability) {
            if (str_starts_with($ability, $prefix)) {
                return substr($ability, strlen($prefix));
            }
        }

        return null;
    }

    private function findTenantEmployee(string $schema, int $employeeId, string $companyId, string $email): ?Employee
    {
        $previousSearchPath = $this->currentSearchPath();

        try {
            DB::statement('SET search_path TO '.$this->formatSearchPath([$schema, 'public']));

            $tenantEmployee = Employee::withoutGlobalScopes()
                ->with('company')
                ->where('id', $employeeId)
                ->where('company_id', $companyId)
                ->where('email', $email)
                ->first();
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }

        if (! $tenantEmployee instanceof Employee) {
            return null;
        }

        return $tenantEmployee;
    }

    private function replaceRequestUser(Employee $originalEmployee, Employee $tenantEmployee, Request $request): Employee
    {
        rescue(
            static fn () => $tenantEmployee->withAccessToken($originalEmployee->currentAccessToken()),
            null,
            false
        );
        $request->setUserResolver(fn (): Employee => $tenantEmployee);

        return $tenantEmployee;
    }

    private function currentSearchPath(): ?string
    {
        $result = DB::scalar('SHOW search_path');

        return is_scalar($result) ? (string) $result : null;
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
