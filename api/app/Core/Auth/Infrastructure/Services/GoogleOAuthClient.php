<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * #7962 — accès à Socialite isolé dans l'Infrastructure.
 *
 * `AuthController` appelait `Socialite::driver('google')` directement, ce qui
 * liait la couche Interfaces à une façade Laravel et rendait les trois flux
 * Google intestables sans réseau. La garde `check-layer-purity.sh` interdit
 * les façades depuis `Application/` : les Actions qui orchestrent le SSO ne
 * peuvent donc pas parler à Socialite elles-mêmes. Ce client est le seul
 * endroit qui connaît Socialite.
 *
 * Il ne décide de RIEN : il rend l'utilisateur Google ou lève. La décision
 * (email vérifié ? compte connu ? 2FA ?) appartient aux Actions.
 */
final class GoogleOAuthClient
{
    /**
     * Issue #5170 — les trois variables sont nécessaires pour construire
     * l'URL de redirection. Absentes, Socialite lève au moment de construire
     * l'URL, ce qui produisait un 500 opaque en production.
     */
    public function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    /**
     * Construit l'URL de consentement Google pour un `state` déjà généré.
     */
    public function redirectUrl(string $state): string
    {
        /** @var GoogleProvider $google */
        $google = Socialite::driver('google');

        return $google->with(['state' => $state])->redirect()->getTargetUrl();
    }

    /**
     * Échange le `code` du callback contre l'identité Google.
     *
     * @throws \Exception quand l'échange échoue (code invalide, provider injoignable)
     */
    public function userFromCallback(): SocialiteUser
    {
        /** @var GoogleProvider $google */
        $google = Socialite::driver('google');

        /** @var SocialiteUser $user */
        $user = $google->stateless()->user();

        return $user;
    }

    /**
     * Échange un access token mobile contre l'identité Google.
     *
     * @throws \Exception quand le token est invalide
     */
    public function userFromToken(string $accessToken): SocialiteUser
    {
        /** @var GoogleProvider $google */
        $google = Socialite::driver('google');

        /** @var SocialiteUser $user */
        $user = $google->stateless()->userFromToken($accessToken);

        return $user;
    }
}
