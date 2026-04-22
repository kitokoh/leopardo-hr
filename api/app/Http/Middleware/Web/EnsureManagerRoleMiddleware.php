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
            abort(403, 'Acces reserve aux managers.');
        }

        if ($roles === []) {
            return $next($request);
        }

        if (! in_array($employee->manager_role, $roles, true)) {
            abort(403, 'Sous-role manager insuffisant.');
        }

        return $next($request);
    }
}
