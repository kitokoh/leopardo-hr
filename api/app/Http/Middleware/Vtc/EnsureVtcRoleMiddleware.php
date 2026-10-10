<?php

declare(strict_types=1);

namespace App\Http\Middleware\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde RBAC de la verticale VTC (BC-34 VTC, VTC-05/#8361).
 *
 * Matrice deny-by-default (docs/architecture/VTC_RBAC.md) :
 *
 *   vtc.admin      → manager `principal` (propriétaire du tenant)
 *   vtc.dispatcher → manager `principal` | `manager` (ops répartition)
 *   vtc.driver     → employé actif rattaché à une fiche chauffeur — le
 *                    périmètre (SES offres/courses) est porté par la
 *                    résolution du chauffeur dans les contrôleurs
 *
 * Tout employé non couvert est refusé (403 deny-by-default). Les ensembles
 * de manager_role sont définis UNE SEULE FOIS dans VtcRoleResolver — la
 * garde les consomme, pas de duplication/divergence (leçon #8185).
 *
 * Alias : `vtc.role` (bootstrap/app.php) — usage :
 *   Route::middleware('vtc.role:driver')->group(...)
 */
final class EnsureVtcRoleMiddleware
{
    /** Rôles gérés par la garde (miroir du manifest VtcManifest). */
    private const SUPPORTED_ROLES = ['admin', 'dispatcher', 'driver'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $employee = $request->user() ?? Auth::user();

        if (! $employee instanceof Employee) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ], 401);
        }

        if ($roles === []) {
            // Aucun rôle demandé = accès refusé (deny-by-default).
            return $this->denied();
        }

        foreach ($roles as $role) {
            if ($this->matches($employee, $role)) {
                return $next($request);
            }
        }

        return $this->denied();
    }

    private function denied(): Response
    {
        return response()->json([
            'error' => 'VTC_ROLE_REQUIRED',
            'message' => 'Your role does not allow this VTC operation.',
        ], 403);
    }

    private function matches(Employee $employee, string $role): bool
    {
        if (! in_array($role, self::SUPPORTED_ROLES, true)) {
            return false;
        }

        if ($role === 'driver') {
            // Chauffeur = employé actif ; le lien vers la fiche chauffeur
            // (user_id) est exigé au niveau des contrôleurs, qui ont besoin
            // de l'identifiant chauffeur pour borner le périmètre.
            return $employee->isEmployee() && $employee->status === 'active';
        }

        if (! $employee->isManager()) {
            return false;
        }

        if ($role === 'admin') {
            return $employee->hasManagerRole(...VtcRoleResolver::ADMIN_MANAGER_ROLES);
        }

        // dispatcher
        return $employee->hasManagerRole(...VtcRoleResolver::DISPATCHER_MANAGER_ROLES);
    }
}
