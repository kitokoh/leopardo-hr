<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #1874 — identifiant de corrélation unique par requête.
 *
 * Lit `X-Correlation-ID` (header canonique) avec repli sur l'historique
 * `X-Request-Id` (rétro-compatibilité, logs structurés existants), sinon
 * génère un UUID. L'identifiant est lié au conteneur (`correlation_id`,
 * consommé par `correlation_id()` — audit de calcul paie #1874) et exposé
 * en réponse via `X-Correlation-ID` (et `X-Request-Id` conservé).
 *
 * Longueur plafonnée à 64 caractères (colonnes d'audit) ; tout header
 * surdimensionné est remplacé par un UUID frais.
 */
class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->sanitize(
            $request->header('X-Correlation-ID') ?: $request->header('X-Request-Id')
        );
        $request->headers->set('X-Correlation-ID', $correlationId);
        $request->headers->set('X-Request-Id', $correlationId);

        app()->instance('correlation_id', $correlationId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Request-Id', $correlationId);

        return $response;
    }

    private function sanitize(mixed $value): string
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > 64) {
            return (string) Str::uuid();
        }

        return $value;
    }
}
