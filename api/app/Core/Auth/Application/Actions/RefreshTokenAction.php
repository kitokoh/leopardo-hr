<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Use Case : Rotation du token d'accès (POST /auth/refresh-token).
 *
 * Creates a new token with the same name and abilities as the current one,
 * then deletes the old one (single-use rotation).
 *
 * Fix #5581 — rotation sous verrou pessimiste :
 * Sans verrou, deux appels simultanés à POST /auth/refresh-token pour le
 * même token produisaient deux nouveaux tokens valides (race condition).
 * Le SELECT FOR UPDATE garantit qu'un seul pivot aboutit ; si le token a
 * déjà été supprimé par une requête concurrente, on renvoie 409 pour que
 * le client réessaie avec son nouveau token.
 *
 * @return array{token: string, token_type: string, token_expires_at: ?string}
 */
final class RefreshTokenAction
{
    /**
     * @return array{token: string, token_type: string, token_expires_at: ?string}
     */
    public function execute(HasApiTokens $user): array
    {
        /** @var PersonalAccessToken $currentToken */
        $currentToken = $user->currentAccessToken();

        $expirationMinutes = (int) config('sanctum.expiration', 0);
        $expiresAt         = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
        $tokenId           = $currentToken->getKey();

        /** @var array{token: string, token_type: string, token_expires_at: ?string} $result */
        $result = DB::transaction(static function () use ($user, $currentToken, $tokenId, $expiresAt): array {
            // Verrou pessimiste — bloque les rotations concurrentes sur ce token.
            /** @var PersonalAccessToken|null $fresh */
            $fresh = PersonalAccessToken::lockForUpdate()->find($tokenId);

            // Token déjà pivoté par une requête concurrente (ou révoqué) :
            // 409 pour que le client utilise le token reçu dans l'autre réponse.
            if ($fresh === null) {
                abort(409, 'TOKEN_ALREADY_ROTATED');
            }

            $newToken = $user->createToken(
                $currentToken->name ?? 'api',
                $currentToken->abilities ?? ['*'],
                $expiresAt,
            );

            $fresh->delete();

            return [
                'token'            => $newToken->plainTextToken,
                'token_type'       => 'Bearer',
                'token_expires_at' => $expiresAt?->toIso8601String(),
            ];
        });

        return $result;
    }
}
