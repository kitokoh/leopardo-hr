<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — accès à
 * un module délégable pour des routes qui n'exigent PAS un manager :
 * `api.module.grant:<module_key>` passe si le collaborateur porte un grant
 * explicite du module (`employee_module_grants`) **OU** est manager du tenant.
 *
 * Décision documentée pour `/support-tickets` (comportement historique :
 * ouvert à TOUT employé du tenant, PA2-COMM-012) : les MANAGERS conservent
 * l'accès intégral (le principal inclus — il a de toute façon implicitement
 * tout via `hasModuleGrant`), et les employés non-managers doivent désormais
 * porter le grant `support` — c'est précisément la délégation voulue par la
 * spec (« la gestion des tickets est délégable à un collaborateur comme
 * n'importe quel module »). Fail-closed pour l'employé sans grant.
 */
class EnsureModuleGrantMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $employee = $request->user() ?? Auth::user();

        if ($employee !== null) {
            if (method_exists($employee, 'hasModuleGrant') && $employee->hasModuleGrant($moduleKey)) {
                return $next($request);
            }

            // Comportement historique préservé : un manager du tenant garde
            // l'accès sans grant (le grant ne restreint jamais un manager).
            if (method_exists($employee, 'isManager') && $employee->isManager()) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'MODULE_ACCESS_REQUIRED',
            'message' => 'This module requires an explicit grant or manager access.',
            'localized_message' => __('errors.MODULE_ACCESS_REQUIRED'),
        ], 403);
    }
}
