<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StructuredLogging
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (int) round((microtime(true) - $start) * 1000);

        if ($this->shouldLog($request)) {
            Log::channel('structured')->info('http_request', [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $duration,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id ?? null,
                'request_id' => $request->header('X-Request-Id'),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        $path = $request->path();

        if (str_starts_with($path, 'api/v1/health')) {
            return false;
        }

        return true;
    }
}
