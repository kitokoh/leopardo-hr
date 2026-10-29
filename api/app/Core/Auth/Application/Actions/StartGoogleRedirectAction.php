<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Application\DTOs\GoogleSsoResult;
use App\Core\Auth\Infrastructure\Services\GoogleOAuthClient;
use App\Core\Auth\Infrastructure\Services\GoogleSsoAuditLogger;
use Throwable;

/**
 * #7962 — démarrage du flux Google (construction de l'URL de consentement).
 *
 * Extrait de `AuthController::redirectToGoogle`. Le contrôleur gardait trois
 * responsabilités mêlées : générer le `state` anti-CSRF, le stocker en session,
 * et construire l'URL. Seule la troisième est du métier — les deux premières
 * sont du transport HTTP et restent dans le contrôleur.
 *
 * Les deux échecs distincts de l'issue #5170 et #6670 restent distincts :
 * configuration absente (503 `GOOGLE_OAUTH_NOT_CONFIGURED`, actionnable par
 * l'exploitant) et Socialite en échec malgré la configuration (503
 * `GOOGLE_OAUTH_UNAVAILABLE`). Les fusionner priverait l'exploitant du seul
 * indice qui distingue « il manque une variable » de « Google est injoignable ».
 */
final class StartGoogleRedirectAction
{
    public function __construct(
        private readonly GoogleOAuthClient $client,
        private readonly GoogleSsoAuditLogger $auditLogger,
    ) {}

    public function execute(string $state): GoogleSsoResult
    {
        if (! $this->client->configured()) {
            $this->auditLogger->notConfigured();

            return GoogleSsoResult::error(
                'GOOGLE_OAUTH_NOT_CONFIGURED',
                __('errors.GOOGLE_OAUTH_NOT_CONFIGURED'),
                503,
            );
        }

        try {
            return GoogleSsoResult::redirect($this->client->redirectUrl($state));
        } catch (Throwable $exception) {
            $this->auditLogger->exchangeFailed('redirect', $exception->getMessage());

            return GoogleSsoResult::error(
                'GOOGLE_OAUTH_UNAVAILABLE',
                __('errors.SERVICE_UNAVAILABLE'),
                503,
            );
        }
    }
}
