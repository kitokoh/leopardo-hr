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
            // #4697 (audit 360° 2026-08-16) : forme d'erreur standard de l'API
            // (error + message + localized_message) — avant, ce middleware était
            // la seule réponse 403 sans ce contrat.
            $code = 'AI_FEATURE_DISABLED';

            return response()->json([
                'error' => $code,
                'message' => $code,
                'localized_message' => __('errors.'.$code),
            ], 403);
        }

        return $next($request);
    }
}
