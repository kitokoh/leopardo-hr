<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AIFeatureCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai.enabled', false)) {
            return response()->json([
                'message' => 'AI feature is not enabled. Set AI_ENABLED=true in your environment.',
            ], 403);
        }

        return $next($request);
    }
}
