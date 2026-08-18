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
            // #4812 : message localisé ×4 (avant : EN en dur).
            abort(403, __('errors.AI_ANALYTICS_ACCESS_REQUIRED'));
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
