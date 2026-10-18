<?php

declare(strict_types=1);

namespace App\Http\Middleware\Trade;

use App\Core\Auth\Domain\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde RBAC de la verticale Trade (BC-36 TRADE, TRADE-001/#8476).
 *
 * Matrice deny-by-default (spec docs/specifications/VERTICAL_TRADE_OS.md §2)
 * portée par `TradeRoleResolver` — source unique des ensembles de
 * `manager_role` (leçon #8185, pattern EnsureVtcRoleMiddleware VTC-05) :
 *
 *   trade.role:admin               → manager `principal`
 *   trade.role:purchase|sales|logistics → managers opérationnels + grants `trade.*`
 *   trade.role:reports             → tout manager_role + grant `trade.reports`
 *
 * Tout employé non couvert est refusé (403 deny-by-default).
 *
 * Alias : `trade.role` (bootstrap/app.php) — usage :
 *   Route::middleware('trade.role:purchase')->group(...)
 */
final class EnsureTradeRoleMiddleware
{
    public function __construct(
        private readonly TradeRoleResolver $resolver = new TradeRoleResolver,
    ) {}

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
            if (! in_array($role, TradeRoleResolver::SUPPORTED_ROLES, true)) {
                continue;
            }

            if ($this->resolver->hasRole($employee, $role)) {
                return $next($request);
            }
        }

        return $this->denied();
    }

    private function denied(): Response
    {
        return response()->json([
            'error' => 'TRADE_ROLE_REQUIRED',
            'message' => 'Your role does not allow this Trade operation.',
        ], 403);
    }
}
