<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #7479 — `GET /auth/me` répondait 500 par intermittence en production
 * (« une connexion valide présentée comme un échec »).
 *
 * Cause mesurée (journaux du service, 2026-09-15) :
 *
 *   SQLSTATE[0A000]: Feature not supported: 7 ERROR: cached plan must not change
 *   result type (Connection: pgsql, Host: …-pooler.eu-west-2.aws.neon.tech …)
 *
 * Le pooler Postgres (Neon, mode transaction) réutilise des backends dont le
 * cache de plans est périmé après un changement de schéma (déploiement) : la
 * requête échoue sur une connexion pourtant valide, alors qu'un simple nouvel
 * essai sur une connexion fraîche réussit.
 *
 * Ce middleware :
 *   1. **rejoue une fois** la requête quand l'erreur est transitoire — mais
 *      **uniquement pour les méthodes idempotentes** (GET/HEAD/OPTIONS), afin de
 *      ne jamais risquer un double écrit ;
 *   2. si le second essai échoue aussi, renvoie une réponse **dégradée
 *      documentée** (503 + code stable) au lieu d'un 500 nu : le client peut
 *      dire « service momentanément indisponible » et non « vos identifiants
 *      sont faux » ;
 *   3. laisse passer (rethrow) toute autre `QueryException` : une vraie erreur
 *      applicative ne doit pas être masquée par un retry.
 *
 * Le levier de fond (désactiver les *prepared statements* côté serveur sur les
 * connexions poolées) est documenté dans
 * `docs/ops/POSTGRES_POOLER_PREPARED_STATEMENTS.md` : il reste à arbitrer côté
 * hébergeur, ce middleware rend l'incident non bloquant en attendant.
 */
final class RetryTransientDatabaseErrors
{
    /**
     * SQLSTATE considérés comme transitoires (panne d'infrastructure, pas de
     * bug applicatif) : plan de requête invalide après un DDL, transaction
     * PostgreSQL déjà avortée par une erreur précédente, connexion coupée.
     */
    private const TRANSIENT_SQLSTATES = [
        '0A000', // feature_not_supported — « cached plan must not change result type »
        '25P02', // in_failed_sql_transaction
        '08000', // connection_exception
        '08003', // connection_does_not_exist
        '08006', // connection_failure
        '08001', // sqlclient_unable_to_establish_sqlconnection
        '57P01', // admin_shutdown (maintenance Render/Neon)
        '57P03', // cannot_connect_now
    ];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (QueryException $exception) {
            // Méthodes non idempotentes : rejouer risquerait un double écriture.
            // Erreurs non transitoires : ce n'est pas notre rôle de les masquer.
            if (! $this->isIdempotent($request) || ! $this->isTransient($exception)) {
                throw $exception;
            }

            Log::warning('database.transient_error_retry', [
                'path' => $request->path(),
                'method' => $request->method(),
                'sqlstate' => $exception->getCode(),
            ]);

            // Connexion fraîche : c'est elle qui repart avec un cache de plans
            // valide (le pooler peut avoir servi un backend périmé).
            $this->reconnect();

            try {
                return $next($request);
            } catch (QueryException $retryException) {
                if (! $this->isTransient($retryException)) {
                    throw $retryException;
                }

                Log::error('database.transient_error_unavailable', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'attempts' => 2,
                    'sqlstate' => $retryException->getCode(),
                ]);

                return $this->unavailableResponse();
            }
        }
    }

    private function isIdempotent(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function isTransient(QueryException $exception): bool
    {
        $sqlstate = strtoupper((string) $exception->getCode());
        if (in_array($sqlstate, self::TRANSIENT_SQLSTATES, true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        foreach ([
            'cached plan must not change result type',
            'current transaction is aborted',
            'server closed the connection unexpectedly',
            'terminating connection due to administrator command',
            'connection reset by peer',
            'ssl connection has been closed',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function reconnect(): void
    {
        try {
            DB::connection()->disconnect();
        } catch (\Throwable) {
            // Best-effort : si la déconnexion échoue, Laravel rétablira la
            // connexion de toute façon au prochain accès.
        }
    }

    /**
     * Réponse dégradée explicite : le client sait que le service (et non sa
     * session ni ses identifiants) est en cause, et qu'un nouvel essai a du sens.
     */
    private function unavailableResponse(): JsonResponse
    {
        return response()->json([
            'error' => 'SERVICE_UNAVAILABLE',
            'code' => 'DB_TRANSIENT',
            'retryable' => true,
            'message' => __('errors.SERVICE_UNAVAILABLE'),
        ], 503)->header('Retry-After', '2');
    }
}
