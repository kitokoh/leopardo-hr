<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // #5581 — le nouveau token est transporté dans le CORPS JSON de la
        // réponse. Si la réponse n'est pas du JSON (aucun corps exploitable),
        // on NE rotte PAS : l'ancien token reste valide plutôt que de
        // déconnecter silencieusement le client sans lui remettre de token.
        $content = $response->getContent();

        if (! is_string($content) || $content === '' || ! json_validate($content)) {
            return $response;
        }

        // #5581 — Rotation ATOMIQUE (transaction + verrou pessimiste) : deux
        // requêtes concurrentes dans la fenêtre de rafraîchissement sont
        // sérialisées ; une seule émet le nouveau token, l'autre constate que
        // la ligne a disparu et ne fait rien (la réponse concurrente porte le
        // nouveau token). Fin de la duplication de tokens valides.
        $newToken = DB::transaction(function () use ($user, $currentToken, $newExpiresAt) {
            $locked = PersonalAccessToken::query()
                ->lockForUpdate()
                ->find($currentToken->getKey());

            if ($locked === null) {
                return null;
            }

            $rotated = $user->createToken(
                $currentToken->name ?? 'api',
                $currentToken->abilities ?? ['*'],
                $newExpiresAt
            );

            $locked->delete();

            return $rotated;
        });

        if ($newToken === null) {
            // Rotation déjà effectuée par une requête concurrente : la
            // réponse ne transporte aucun token (celui de la requête
            // gagnante fait foi).
            return $response;
        }

        // #5581 — le nouveau token part dans le corps JSON, plus jamais dans
        // un header : tout intermédiaire qui loggue les headers (reverse
        // proxy, CDN) capturait X-New-Token, et toute réponse perdue
        // désynchronisait client/serveur. Le header X-Token-Refreshed (non
        // sensible) reste pour signaler la rotation.
        $payload = json_decode($content, true);

        if (is_array($payload)) {
            $payload['token_refreshed'] = true;
            $payload['token'] = $newToken->plainTextToken;
            $payload['token_type'] = 'Bearer';
            $payload['token_expires_at'] = $newExpiresAt->toIso8601String();

            $response->setContent((string) json_encode($payload));
        }

        $response->headers->set('X-Token-Refreshed', 'true');

        return $response;
    }
}
