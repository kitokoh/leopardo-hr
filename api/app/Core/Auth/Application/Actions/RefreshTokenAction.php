<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Exceptions\TokenRotationConflictException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Use Case : Rotation du token d'accès.
 *
 * Creates a new token with the same name and abilities as the current one,
 * then deletes the old one (single-use rotation).
 *
 * #5581 — Rotation ATOMIQUE : la création + suppression se font dans une
 * transaction avec verrou pessimiste (`SELECT ... FOR UPDATE`) sur la ligne
 * du token courant. Deux requêtes concurrentes dans la fenêtre de
 * rafraîchissement sont sérialisées : la première gagne, les suivantes
 * trouvent la ligne supprimée et lèvent `TokenRotationConflictException`
 * (409 TOKEN_ALREADY_ROTATED) — plus jamais deux tokens valides émis pour
 * le même token source (un token volé ne peut plus être rafraîchi en boucle
 * par course avec le client légitime).
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
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        return DB::transaction(function () use ($user, $currentToken, $expiresAt): array {
            // #5581 — relecture de la ligne sous verrou : les rotations
            // concurrentes du même token sont sérialisées (FOR UPDATE bloque
            // la transaction rivale jusqu'au commit de la première).
            $locked = PersonalAccessToken::query()
                ->lockForUpdate()
                ->find($currentToken->getKey());

            if ($locked === null) {
                // Le token a déjà été roté par une requête concurrente (la
                // ligne n'existe plus) : ne PAS émettre un deuxième token.
                // Le client doit utiliser celui renvoyé par la réponse
                // concurrente (ou se ré-authentifier).
                throw TokenRotationConflictException::alreadyRotated();
            }

            $newToken = $user->createToken(
                $currentToken->name ?? 'api',
                $currentToken->abilities ?? ['*'],
                $expiresAt,
            );

            $locked->delete();

            return [
                'token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'token_expires_at' => $expiresAt?->toIso8601String(),
            ];
        });
    }
}
