<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    private const CURRENT_VERSION = 'v1';

    private const SUPPORTED_VERSIONS = ['v1'];

    public function handle(Request $request, Closure $next, string $version = self::CURRENT_VERSION): Response
    {
        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return response()->json([
                'error' => 'UNSUPPORTED_API_VERSION',
                'message' => __('API version :version is not supported. Supported versions: :supported', [
                    'version' => $version,
                    'supported' => implode(', ', self::SUPPORTED_VERSIONS),
                ]),
            ], 400);
        }

        $request->attributes->set('api_version', $version);

        Log::channel('structured')->debug('API request', [
            'api_version' => $version,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-API-Version', $version);

        return $response;
    }
}
