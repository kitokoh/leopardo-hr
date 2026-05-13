<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAIAnalyticsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasManagerRole') || ! $user->hasManagerRole('principal', 'rh')) {
            abort(403, 'AI analytics access requires Principal or RH manager role.');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
