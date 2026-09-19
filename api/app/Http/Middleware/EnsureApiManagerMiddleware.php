<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restriction d'accès manager par `manager_role`, étendue (issue #7761) aux
 * GRANTS DE MODULES composables : un paramètre `module:<module_key>` déclare
 * le module du groupe de routes (mapping route-group→module_key EXPLICITE,
 * ex. `api.manager:marketing,principal,module:marketing`). L'accès passe si
 * le `manager_role` est autorisé (comportement historique) **OU** si le
 * collaborateur porte un grant explicite du module (`employee_module_grants`,
 * délégation par le principal — spec MISSION_ESPACE_CLIENT §3.1). Le
 * `principal` a implicitement tout (`Employee::hasModuleGrant`).
 *
 * Sans paramètre `module:`, le comportement est strictement inchangé.
 */
class EnsureApiManagerMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Allowed manager_role values (empty = any manager),
     *                            plus an optional `module:<key>` token naming the
     *                            module a grant can unlock (#7761).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $moduleKey = null;
        $allowedRoles = [];

        foreach ($roles as $param) {
            if (str_starts_with($param, 'module:')) {
                $moduleKey = substr($param, strlen('module:'));

                continue;
            }

            $allowedRoles[] = $param;
        }

        $employee = $request->user() ?? Auth::user();

        // #7761 — grant explicite du module : la délégation par le principal
        // ouvre le module à un collaborateur quel que soit son rôle (y compris
        // non-manager). Vérifié AVANT l'exigence manager pour cette raison.
        if (
            $moduleKey !== null
            && $moduleKey !== ''
            && $employee !== null
            && method_exists($employee, 'hasModuleGrant')
            && $employee->hasModuleGrant($moduleKey)
        ) {
            return $next($request);
        }

        if (! $employee || ! method_exists($employee, 'isManager') || ! $employee->isManager()) {
            return response()->json([
                'error' => 'MANAGER_REQUIRED',
                'message' => 'This endpoint requires manager access.',
                'localized_message' => __('errors.MANAGER_REQUIRED', [], 'fr'),
            ], 403);
        }

        if ($allowedRoles !== [] && method_exists($employee, 'hasManagerRole') && ! $employee->hasManagerRole(...$allowedRoles)) {
            return response()->json([
                'error' => 'INSUFFICIENT_ROLE',
                'message' => 'Your manager role does not have access to this resource.',
                'localized_message' => __('errors.INSUFFICIENT_ROLE', [], 'fr'),
            ], 403);
        }

        return $next($request);
    }
}
