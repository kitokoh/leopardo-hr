<?php

declare(strict_types=1);

namespace App\Http\Middleware\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde RBAC du module Delivery (BC-26-D05, issue #6294).
 *
 * Matrice deny-by-default (spec SOLUTION_DELIVERY.md §4, docs
 * `docs/architecture/DELIVERY_RBAC.md`) :
 *
 *   delivery.admin      → manager `principal` (propriétaire du tenant)
 *   delivery.dispatcher → manager `principal` | `manager` (ops livraison)
 *   delivery.manager    → manager `principal` | `manager` | `rh` (lecture/rapports)
 *   delivery.reports    → alias de `delivery.manager` (parité manifest)
 *   delivery.rider      → employé actif (le périmètre est SA tournée :
 *                         driver_id = id de l'employé — voir Policies)
 *
 * Tout employé non couvert est refusé (403 deny-by-default). Les décisions
 * par ressource (ownership livreur, scope tenant) sont portées par les
 * Policies `DeliveryPolicy` / `DeliveryRoutePolicy` / `DeliveryPolicy`.
 *
 * Alias : `delivery.role` (bootstrap/app.php) — usage dans les routes :
 *   Route::middleware('delivery.role:dispatcher')->group(...)
 */
final class EnsureDeliveryRoleMiddleware
{
    /** Rôles gérés par la garde (miroir du manifest DeliveryManifest). */
    private const SUPPORTED_ROLES = ['admin', 'dispatcher', 'manager', 'rider', 'reports'];

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
                'localized_message' => __('errors.UNAUTHENTICATED', [], 'fr'),
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
            'error' => 'DELIVERY_ROLE_REQUIRED',
            'message' => 'Your role does not allow this delivery operation.',
            'localized_message' => __('errors.DELIVERY_ROLE_REQUIRED', [], 'fr'),
        ], 403);
    }

    private function matches(Employee $employee, string $role): bool
    {
        if (! in_array($role, self::SUPPORTED_ROLES, true)) {
            return false;
        }

        if ($role === 'rider') {
            // Livreur = employé actif ; le périmètre est SA tournée du jour
            // (driver_id), porté par les Policies + le scope des requêtes.
            return $employee->isEmployee() && $employee->status === 'active';
        }

        // admin / dispatcher / manager / reports : tous des managers.
        if (! $employee->isManager()) {
            return false;
        }

        return match ($role) {
            'admin' => $employee->manager_role === 'principal',
            'dispatcher' => in_array($employee->manager_role, ['principal', 'manager'], true),
            'manager', 'reports' => in_array($employee->manager_role, ['principal', 'manager', 'rh'], true),
            default => false,
        };
    }
}
