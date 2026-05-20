<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TokenAutoRefreshMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user === null) {
            return $response;
        }

        $currentToken = $user->currentAccessToken();
        if (! $currentToken instanceof PersonalAccessToken) {
            return $response;
        }

        $expirationMinutes = (int) config('sanctum.expiration', 0);
        if ($expirationMinutes <= 0) {
            return $response;
        }

        $createdAt = $currentToken->created_at;
        if ($createdAt === null || $createdAt === false) {
            return $response;
        }

        $refreshWindowMinutes = (int) config('sanctum.auto_refresh_window', 1440);
        $expiresAt = $createdAt->copy()->addMinutes($expirationMinutes);
        $refreshThreshold = $expiresAt->copy()->subMinutes($refreshWindowMinutes);

        if (now()->lt($refreshThreshold)) {
            return $response;
        }

        $newExpiresAt = now()->addMinutes($expirationMinutes);
        $newToken = $user->createToken(
            $currentToken->name ?? 'api',
            $currentToken->abilities ?? ['*'],
            $newExpiresAt
        );

        $currentToken->delete();

        $response->headers->set('X-Token-Refreshed', 'true');
        $response->headers->set('X-New-Token', $newToken->plainTextToken);
        $response->headers->set('X-Token-Expires-At', $newExpiresAt->toIso8601String());

        return $response;
    }
}
