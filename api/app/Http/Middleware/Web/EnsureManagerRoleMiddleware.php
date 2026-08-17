<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware qui autorise uniquement certains sous-roles de manager.
 *
 * Usage (routes) :
 *   Route::middleware(['auth:web','tenant','manager_role:principal,rh'])->group(...)
 *
 * Si aucun sous-role n'est passe, tous les managers (peu importe manager_role)
 * sont acceptes.
 */
class EnsureManagerRoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $employee = $request->user();

        if (! $employee || ! method_exists($employee, 'isManager') || ! $employee->isManager()) {
            // #4812 : message localisé ×4 (avant : FR non accentué en dur).
            abort(403, __('errors.MANAGER_ROLE_REQUIRED'));
        }

        if ($roles === []) {
            return $next($request);
        }

        if (! in_array($employee->manager_role, $roles, true)) {
            abort(403, __('errors.MANAGER_ROLE_INSUFFICIENT'));
        }

        return $next($request);
    }
}
