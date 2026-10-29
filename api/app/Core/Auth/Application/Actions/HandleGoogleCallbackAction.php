<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Application\DTOs\GoogleSsoResult;
use App\Core\Auth\Infrastructure\Services\GoogleOAuthClient;
use App\Core\Auth\Infrastructure\Services\GoogleSsoAuditLogger;
use Exception;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * #7962 — callback OAuth web (échange du `code` puis résolution d'identité).
 *
 * Extrait de `AuthController::handleGoogleCallback`. Le contrôleur ne garde
 * que ce qui lui appartient : valider le `state` en session, lire l'intention
 * d'inscription, et traduire le résultat en réponse HTTP.
 *
 * L'intention `signup` est un cas de bord volontairement conservé : quand
 * l'identité Google est vérifiée mais qu'aucun employé n'existe, on ne
 * provisionne RIEN (#3724) — on renvoie l'identité vérifiée pour pré-remplir
 * le tunnel d'essai, qui reste seul responsable de la création du tenant.
 */
final class HandleGoogleCallbackAction
{
    public function __construct(
        private readonly GoogleOAuthClient $client,
        private readonly ResolveGoogleIdentityAction $resolveIdentity,
        private readonly GoogleSsoAuditLogger $auditLogger,
    ) {}

    /**
     * @param  string  $intent  `login` ou `signup`
     * @param  string|null  $plan  Plan demandé au tunnel d'inscription
     * @param  array<int, string>  $rememberCookies  Valeurs des cookies « appareil de confiance »
     */
    public function execute(
        string $intent,
        ?string $plan,
        array $rememberCookies = [],
    ): GoogleSsoResult {
        try {
            $googleUser = $this->client->userFromCallback();
        } catch (Exception $exception) {
            $this->auditLogger->exchangeFailed('callback', $exception->getMessage());

            return GoogleSsoResult::error(
                'GOOGLE_AUTH_FAILED',
                __('errors.GOOGLE_AUTH_FAILED'),
                422,
            );
        }

        $resolved = $this->resolveIdentity->execute(
            googleUser: $googleUser,
            flow: 'callback',
            rememberCookies: $rememberCookies,
            deviceName: null,
            allowDemoProvisioning: true,
        );

        if ($resolved['email_not_verified']) {
            return GoogleSsoResult::error(
                'EMAIL_NOT_VERIFIED',
                __('errors.EMAIL_NOT_VERIFIED'),
                401,
            );
        }

        if ($resolved['identity_mismatch']) {
            return GoogleSsoResult::error(
                'GOOGLE_IDENTITY_MISMATCH',
                __('errors.GOOGLE_IDENTITY_MISMATCH'),
                401,
            );
        }

        /** @var \App\Core\Auth\Domain\Models\Employee|null $employee */
        $employee = $resolved['employee'];

        if ($employee === null) {
            if ($intent === 'signup') {
                return $this->signupResult($googleUser, $plan);
            }

            // Issue #5171 — message localisé ×4 (parité i18n) : le parcours
            // invitation-first (#2617) crée toujours la ligne employé en amont ;
            // le 401 guide le nouvel utilisateur vers son admin.
            return GoogleSsoResult::error(
                'UNKNOWN_ACCOUNT',
                __('errors.UNKNOWN_ACCOUNT'),
                401,
            );
        }

        if ($resolved['mfa_challenge'] !== null) {
            return GoogleSsoResult::mfaChallenge(
                $resolved['mfa_challenge']['token'],
                $resolved['mfa_challenge']['expires_in'],
            );
        }

        $token = $employee->createToken('google-auth');

        return GoogleSsoResult::authenticated($employee, $token->plainTextToken);
    }

    private function signupResult(SocialiteUser $googleUser, ?string $plan): GoogleSsoResult
    {
        $this->auditLogger->signupIntent((string) $googleUser->getEmail(), $plan);

        return GoogleSsoResult::signup(
            email: (string) $googleUser->getEmail(),
            firstName: (string) ($googleUser->offsetGet('given_name') ?? ''),
            lastName: (string) ($googleUser->offsetGet('family_name') ?? ''),
            plan: $plan,
        );
    }
}
