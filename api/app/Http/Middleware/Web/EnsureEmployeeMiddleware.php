<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware qui autorise tout employe authentifie (manager ou non),
 * tant que son compte est actif et rattache a une entreprise active.
 *
 * Utilise pour l'espace personnel /me/*.
 */
class EnsureEmployeeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();

        if (! $employee) {
            abort(403);
        }

        if (($employee->status ?? null) !== 'active') {
            // #4878 : littéral FR déplacé au catalogue errors.*
            abort(403, __('errors.EMPLOYEE_INACTIVE'));
        }

        $company = $employee->company;
        if ($company && in_array($company->status, ['suspended', 'expired'], true)) {
            // #4878 : littéral FR déplacé au catalogue errors.*
            abort(403, __('errors.COMPANY_SUSPENDED_EXPIRED'));
        }

        return $next($request);
    }
}
