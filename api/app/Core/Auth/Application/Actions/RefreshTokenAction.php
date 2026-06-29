<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use Laravel\Sanctum\Contracts\HasApiTokens;

/**
 * Use Case : Rotation du token d'accès.
 *
 * Creates a new token with the same name and abilities as the current one,
 * then deletes the old one (single-use rotation).
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
        /** @var \Laravel\Sanctum\PersonalAccessToken $currentToken */
        $currentToken = $user->currentAccessToken();

        $expirationMinutes = (int) config('sanctum.expiration', 0);
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        $newToken = $user->createToken(
            $currentToken->name ?? 'api',
            $currentToken->abilities ?? ['*'],
            $expiresAt,
        );

        $currentToken->delete();

        return [
            'token'            => $newToken->plainTextToken,
            'token_type'       => 'Bearer',
            'token_expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
