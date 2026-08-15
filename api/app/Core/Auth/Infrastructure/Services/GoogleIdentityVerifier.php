<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Infrastructure\Services\SSO\OidcIdTokenValidator;
use App\Exceptions\GoogleTokenInvalidException;

/**
 * Issue #3941 — vérification serveur des ID tokens Google pour
 * /api/v1/user/google-signin (comptes ordinaires).
 *
 * L'identité (sub/email/name/picture) est dérivée UNIQUEMENT des claims du
 * token vérifié (signature RS256 contre le JWKS Google, iss, aud, exp,
 * email_verified) — les champs fournis par le client ne sont jamais crus.
 *
 * Clients autorisés (aud) : variables d'environnement GOOGLE_CLIENT_ID,
 * GOOGLE_WEB_CLIENT_ID, GOOGLE_ANDROID_CLIENT_ID, GOOGLE_IOS_CLIENT_ID.
 * Si aucune n'est configurée (dev/démo), la signature + iss + exp restent
 * obligatoires (le contrôle d'audience est sauté, jamais la signature).
 */
final class GoogleIdentityVerifier
{
    private const GOOGLE_ISSUER = 'https://accounts.google.com';

    private const GOOGLE_JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(private readonly OidcIdTokenValidator $idTokenValidator)
    {
    }

    /**
     * @return array{google_id: string, email: string, first_name: string, last_name: string, avatar_url: ?string}
     *
     * @throws GoogleTokenInvalidException
     */
    public function verify(string $idToken): array
    {
        try {
            $claims = $this->idTokenValidator->validate($idToken, [
                'issuer' => self::GOOGLE_ISSUER,
                'client_id' => (string) config('services.google.client_id', ''),
                'audiences' => $this->allowedAudiences(),
                'nonce' => null,
                'jwks_uri' => self::GOOGLE_JWKS_URI,
            ]);
        } catch (\RuntimeException $e) {
            throw new GoogleTokenInvalidException($e->getMessage());
        }

        $googleId = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? '');

        if ($googleId === '' || $email === '') {
            throw new GoogleTokenInvalidException('Le jeton Google ne contient pas d\'identité (sub/email).');
        }

        if (($claims['email_verified'] ?? false) !== true) {
            // Fail-closed : un email non vérifié par Google ne peut pas
            // provisionner un compte (politique d'inscription #3724).
            throw new GoogleTokenInvalidException('L\'email Google n\'est pas vérifié.');
        }

        $fullName = trim((string) ($claims['name'] ?? ''));
        $nameParts = $fullName === '' ? [] : preg_split('/\s+/', $fullName);

        return [
            'google_id' => $googleId,
            'email' => $email,
            'first_name' => (string) ($nameParts[0] ?? ''),
            'last_name' => isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '',
            'avatar_url' => isset($claims['picture']) && is_string($claims['picture'])
                ? $claims['picture']
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedAudiences(): array
    {
        return array_values(array_filter([
            config('services.google.client_id'),
            config('services.google.web_client_id'),
            config('services.google.android_client_id'),
            config('services.google.ios_client_id'),
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
