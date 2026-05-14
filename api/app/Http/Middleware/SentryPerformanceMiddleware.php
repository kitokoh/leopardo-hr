<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Sentry APM — performance traces on critical endpoints.
 *
 * Attaches custom Sentry span tags for payroll, AI and attendance routes
 * so that slow transactions surface in the Sentry dashboard with context.
 *
 * Configure SENTRY_LARAVEL_DSN + SENTRY_TRACES_SAMPLE_RATE in .env.
 */
class SentryPerformanceMiddleware
{
    private const CRITICAL_PREFIXES = [
        'api/v1/payroll' => 'payroll',
        'api/v1/pay-slips' => 'payroll',
        'api/v1/ai/' => 'ai',
        'api/v1/attendance' => 'attendance',
        'api/v1/absences' => 'absences',
        'api/v1/contracts' => 'contracts',
        'api/v1/export' => 'export',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $domain = $this->resolveDomain($path);

        if ($domain !== null && function_exists('Sentry\\configureScope')) {
            configureScope(function (Scope $scope) use ($request, $domain, $path) {
                $scope->setTag('domain', $domain);
                $scope->setTag('route', $request->method().' /'.$path);
                $scope->setContext('http', [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'query' => $request->query(),
                ]);
            });
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    private function resolveDomain(string $path): ?string
    {
        foreach (self::CRITICAL_PREFIXES as $prefix => $domain) {
            if (str_starts_with($path, $prefix)) {
                return $domain;
            }
        }

        return null;
    }
}
