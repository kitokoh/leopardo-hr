<?php

declare(strict_types=1);

namespace App\Http\Middleware\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Delivery\Domain\Support\DeliveryRoleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde RBAC fine de la livraison (BC-26-D05, issue #6294).
 *
 * Usage : `delivery.permission:admin|dispatcher|manager|reports|rider`.
 * Les rôles sont résolus par DeliveryRoleResolver (matrice v1 centralisée) ;
 * l'autorisation livreur est complétée par le scope de propriété
 * (driver_id = employé authentifié) côté contrôleur — jamais par le rôle seul.
 */
class EnsureDeliveryPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $employee = $request->user();

        if (! $employee instanceof Employee) {
            return response()->json([
                'error' => 'DELIVERY_ROLE_REQUIRED',
                'message' => 'DELIVERY_ROLE_REQUIRED',
                'localized_message' => __('errors.FORBIDDEN', [], 'fr'),
            ], 403);
        }

        if (! (new DeliveryRoleResolver())->hasAnyRole($employee, $permissions)) {
            return response()->json([
                'error' => 'DELIVERY_ROLE_REQUIRED',
                'message' => 'DELIVERY_ROLE_REQUIRED',
                'localized_message' => __('errors.FORBIDDEN', [], 'fr'),
            ], 403);
        }

        return $next($request);
    }
}
