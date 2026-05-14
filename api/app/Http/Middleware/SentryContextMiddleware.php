<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Enrich Sentry scope with tenant + user context on every API request.
 *
 * This middleware adds company_id, user role, and plan information to
 * Sentry events so that errors can be triaged per-tenant in the dashboard.
 */
class SentryContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('Sentry\\configureScope')) {
            /** @var Response */
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof Employee) {
            configureScope(function (Scope $scope) use ($user, $request): void {
                $scope->setUser([
                    'id' => (string) $user->id,
                    'email' => $user->email ?? '',
                    'segment' => $user->role,
                ]);

                $scope->setTag('tenant.company_id', (string) $user->company_id);
                $scope->setTag('user.role', $user->role);

                if ($user->manager_role) {
                    $scope->setTag('user.manager_role', $user->manager_role);
                }

                $scope->setContext('tenant', [
                    'company_id' => $user->company_id,
                    'role' => $user->role,
                    'manager_role' => $user->manager_role,
                    'request_id' => $request->header('X-Request-Id', ''),
                ]);
            });
        }

        /** @var Response */
        return $next($request);
    }
}
