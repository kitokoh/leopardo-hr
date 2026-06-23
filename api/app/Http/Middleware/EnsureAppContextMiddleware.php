<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AppContext Middleware — Leopardo Multi-App Architecture
 *
 * Each mobile/web app sends a header X-App-Context identifying itself:
 *   - "manager"   → Leopardo Manager (principal only)
 *   - "rh"        → Leopardo RH (rh manager only)
 *   - "employee"  → Leopardo Employee (all employees)
 *   - "admin"     → Leopardo Admin (super_admin only, handled by platform routes)
 *   - "comptable" → Leopardo Comptable (comptable only)
 *   - "marketing" → Leopardo Marketing (marketing only)
 *
 * This middleware verifies the authenticated user is allowed to use the declared app.
 * It is ADDITIVE: it does not replace auth:sanctum or api.manager checks but
 * adds a cross-cutting "are you using the right app?" layer.
 *
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'tenant', 'app.context:manager'])
 *   Route::middleware(['auth:sanctum', 'tenant', 'app.context:rh,manager']) // multiple allowed
 */
class EnsureAppContextMiddleware
{
    /**
     * Map: X-App-Context value → allowed manager_roles (or 'employee' for regular users).
     *
     * @var array<string, list<string>>
     */
    private const APP_ROLE_MAP = [
        'manager'   => ['principal'],
        'rh'        => ['rh'],
        'comptable' => ['comptable'],
        'marketing' => ['marketing'],
        'dept'      => ['dept'],
        'employee'  => ['employee', 'manager'], // employee app accessible to all authenticated
    ];

    /**
     * @param Closure(Request): Response $next
     * @param string ...$allowedContexts   Accepted X-App-Context values for this route group
     */
    public function handle(Request $request, Closure $next, string ...$allowedContexts): Response
    {
        $appContext = $request->header('X-App-Context');

        // If no header is sent (web, Postman, legacy), skip context check
        if (! $appContext) {
            return $next($request);
        }

        // Unknown app context
        if (! isset(self::APP_ROLE_MAP[$appContext])) {
            return response()->json([
                'error'   => 'UNKNOWN_APP_CONTEXT',
                'message' => "Unknown app context '{$appContext}'.",
            ], 400);
        }

        // Requested route is not available from this app
        if ($allowedContexts !== [] && ! in_array($appContext, $allowedContexts, true)) {
            return response()->json([
                'error'   => 'APP_CONTEXT_MISMATCH',
                'message' => 'This endpoint is not available in your app.',
            ], 403);
        }

        $employee = $request->user();

        if (! $employee) {
            return $next($request); // Let auth guard handle unauthenticated
        }

        // Verify the user's role matches the app they claim to use
        $allowedRoles = self::APP_ROLE_MAP[$appContext];

        $userRole = (string) (method_exists($employee, 'isManager') && $employee->isManager()
            ? ($employee->manager_role ?? 'manager')
            : 'employee');

        if (! in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'error'   => 'APP_ROLE_MISMATCH',
                'message' => "Your role '{$userRole}' is not allowed to use the '{$appContext}' app.",
                'your_role'    => $userRole,
                'required_for' => $appContext,
            ], 403);
        }

        // Inject app context into request for downstream use
        $request->attributes->set('app_context', $appContext);

        return $next($request);
    }
}
