<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();

        if (! $employee) {
            return new JsonResponse(['error' => 'UNAUTHENTICATED'], 401);
        }

        $company = $employee->company;

        if (! $company) {
            return new JsonResponse(['error' => 'COMPANY_NOT_FOUND'], 403);
        }

        if (in_array($company->status, ['suspended', 'expired'], true)) {
            return new JsonResponse(['error' => 'ACCOUNT_SUSPENDED'], 403);
        }

        $request->attributes->set('company', $company);
        app()->instance('current_company', $company);

        return $next($request);
    }
}
