<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #5588 (audit sécurité 2026-08-26) — la documentation API publique
 * (`/docs`, `/api-explorer`, `/tester-guide`, `/docs/openapi.yaml`) n'était
 * protégée par rien alors que la Gate `viewApiDocs` existe (AppServiceProvider).
 *
 * Stratégie : la doc reste publique en dev/staging/test (nécessaire pour le
 * QA, les démos et les tests OpenApiDocsTest) mais **requiert
 * l'authentification en production** (Gate `viewApiDocs` : utilisateur
 * tenant authentifié). En prod, un visiteur anonyme reçoit 403.
 */
class EnsureApiDocsAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! Gate::allows('viewApiDocs')) {
            abort(403, 'FORBIDDEN');
        }

        return $next($request);
    }
}
