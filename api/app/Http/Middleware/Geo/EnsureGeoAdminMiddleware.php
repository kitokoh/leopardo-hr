<?php

declare(strict_types=1);

namespace App\Http\Middleware\Geo;

use App\Core\Auth\Domain\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde admin tenant du module Geo (GEO-05, issue #8354, BC-33 GEO).
 *
 * Réservée aux endpoints de supervision (ex. `GET /v1/geo/capabilities`) :
 * seul un manager `principal` (propriétaire/exploitant du tenant) y accède
 * — même correspondance que `delivery.admin` (matrice DELIVERY_RBAC). Tout
 * autre profil est refusé 403 (deny-by-default).
 *
 * Alias : `geo.admin` (bootstrap/app.php).
 */
final class EnsureGeoAdminMiddleware
{
    /** manager_role donnant le rôle admin tenant (miroir delivery.admin). */
    private const ADMIN_MANAGER_ROLES = ['principal'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user() ?? Auth::user();

        if (! $employee instanceof Employee) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ], 401);
        }

        if (! $employee->isManager() || ! $employee->hasManagerRole(...self::ADMIN_MANAGER_ROLES)) {
            return response()->json([
                'error' => 'GEO_ADMIN_REQUIRED',
                'message' => 'Your role does not allow this geo operation.',
            ], 403);
        }

        return $next($request);
    }
}
