<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    private const CURRENT_VERSION = 'v1';

    /** @var list<string> */
    private const SUPPORTED_VERSIONS = ['v1'];

    public function handle(Request $request, Closure $next): Response
    {
        $routeVersion = $this->routeVersion($request);
        $requestedVersion = $this->requestedVersion($request);

        if ($requestedVersion !== null && $requestedVersion !== $routeVersion) {
            return $this->unsupportedVersion($requestedVersion);
        }

        if (! in_array($routeVersion, self::SUPPORTED_VERSIONS, true)) {
            return $this->unsupportedVersion($routeVersion);
        }

        $request->attributes->set('api_version', $routeVersion);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-API-Version', $routeVersion);
        $response->headers->set('X-API-Supported-Versions', implode(',', self::SUPPORTED_VERSIONS));

        return $response;
    }

    private function routeVersion(Request $request): string
    {
        $segment = $request->segment(2);

        return is_string($segment) && preg_match('/^v[0-9]+$/', $segment) === 1
            ? $segment
            : self::CURRENT_VERSION;
    }

    private function requestedVersion(Request $request): ?string
    {
        $header = $request->header('X-API-Version');

        return is_string($header) && $header !== '' ? strtolower($header) : null;
    }

    private function unsupportedVersion(string $version): JsonResponse
    {
        return response()->json([
            'error' => 'UNSUPPORTED_API_VERSION',
            'message' => 'UNSUPPORTED_API_VERSION',
            'localized_message' => __('errors.UNSUPPORTED_API_VERSION'),
            'supported_versions' => self::SUPPORTED_VERSIONS,
            'requested_version' => $version,
        ], 400)->withHeaders([
            'X-API-Supported-Versions' => implode(',', self::SUPPORTED_VERSIONS),
        ]);
    }
}
