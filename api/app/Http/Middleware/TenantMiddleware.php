<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
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

        $company = $employee->company;

        if (! $company) {
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
        app()->instance('current_company', $company);

        if (DB::getDriverName() === 'pgsql') {
            $rawSchema = $company->schema_name ?: 'shared_tenants';
            $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $rawSchema) ?: 'shared_tenants';
            DB::statement('SET search_path TO "'.$schema.'",public');
        }

        return $next($request);
    }
}
