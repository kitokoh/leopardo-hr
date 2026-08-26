<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-rafraîchit le token Sanctum lorsqu'il entre dans la fenêtre
 * d'expiration (config `sanctum.auto_refresh_window`, défaut 24 h).
 *
 * Fix #5581 — deux problèmes corrigés :
 *
 * 1. Atomicité : createToken + delete sont maintenant enveloppés dans une
 *    transaction DB avec SELECT FOR UPDATE sur le token courant. Sans ce
 *    verrou, deux requêtes concurrentes dans la fenêtre de rafraîchissement
 *    lisaient le même token et émettaient chacune un nouveau token valide
 *    (duplication). Avec le verrou, la seconde requête attendait la fin de
 *    la première ; si le token a déjà été supprimé, elle ignore le pivot.
 *
 * 2. Confidentialité du nouveau token : X-New-Token est SUPPRIMÉ. Les headers
 *    HTTP sont journalisés par les reverse proxies et CDN — les transmettre
 *    en clair constitue un vecteur d'exfiltration. Le nouveau token est
 *    désormais injecté dans le corps JSON de la réponse (clé `_auth`) ; le
 *    client doit le relire depuis le corps, pas depuis les headers.
 */
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
        $expiresAt             = $createdAt->copy()->addMinutes($expirationMinutes);
        $refreshThreshold      = $expiresAt->copy()->subMinutes($refreshWindowMinutes);

        if (now()->lt($refreshThreshold)) {
            return $response;
        }

        // Rotation atomique sous verrou pessimiste (SELECT FOR UPDATE).
        // Si une requête concurrente a déjà pivoté le token, lockForUpdate()
        // bloque jusqu'à la fin de l'autre transaction, puis find() retourne
        // null (le token est supprimé) — on sort proprement sans doublon.
        $tokenId       = $currentToken->getKey();
        $newExpiresAt  = now()->addMinutes($expirationMinutes);

        /** @var array{plain_token: string, expires_at: string}|null $rotated */
        $rotated = DB::transaction(static function () use ($user, $currentToken, $tokenId, $newExpiresAt): ?array {
            /** @var PersonalAccessToken|null $fresh */
            $fresh = PersonalAccessToken::lockForUpdate()->find($tokenId);

            // Token déjà pivoté par une requête concurrente — skip.
            if ($fresh === null) {
                return null;
            }

            $newToken = $user->createToken(
                $currentToken->name ?? 'api',
                $currentToken->abilities ?? ['*'],
                $newExpiresAt,
            );

            $fresh->delete();

            return [
                'plain_token' => $newToken->plainTextToken,
                'expires_at'  => $newExpiresAt->toIso8601String(),
            ];
        });

        if ($rotated === null) {
            // Pivot déjà effectué par une requête concurrente : on retourne
            // la réponse telle quelle ; le client récupérera le nouveau token
            // depuis la réponse parallèle.
            return $response;
        }

        // Indiquer le pivot via des headers SANS exposer le token en clair.
        $response->headers->set('X-Token-Refreshed', 'true');
        $response->headers->set('X-Token-Expires-At', $rotated['expires_at']);

        // Injecter le nouveau token dans le corps JSON de la réponse (#5581).
        // Les headers sont loggués par les proxies/CDN — le token ne doit
        // pas y figurer. Le corps d'une réponse JSON applicative est le seul
        // canal sûr pour transmettre un secret au client.
        if (str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            /** @var array<string, mixed>|null $body */
            $body = json_decode((string) $response->getContent(), true);
            if (is_array($body)) {
                $body['_auth'] = [
                    'token_refreshed' => true,
                    'token'           => $rotated['plain_token'],
                    'expires_at'      => $rotated['expires_at'],
                ];
                $response->setContent((string) json_encode($body));
            }
        }

        return $response;
    }
}
