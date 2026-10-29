<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Application\DTOs\GoogleSsoResult;
use App\Core\Auth\Infrastructure\Services\GoogleOAuthClient;
use App\Core\Auth\Infrastructure\Services\GoogleSsoAuditLogger;
use Exception;

/**
 * #7962 — flux Google mobile (échange d'un access token).
 *
 * Extrait de `AuthController::handleGoogleToken`. Parité stricte avec le
 * callback web sur toutes les gardes de sécurité (email vérifié, résolution
 * bornée au tenant, liaison `google_id`, statut, 2FA, politique MFA) — c'est
 * précisément ce que la duplication rendait fragile.
 *
 * Deux différences assumées, et elles sont structurelles :
 *   - pas de cookie « appareil de confiance » : un client mobile n'en a pas,
 *     donc le challenge TOTP est systématique si la 2FA est activée ;
 *   - pas d'auto-provisionnement, même en mode démo : le mobile n'a pas de
 *     tunnel d'inscription pour recueillir le consentement.
 */
final class HandleGoogleTokenAction
{
    public function __construct(
        private readonly GoogleOAuthClient $client,
        private readonly ResolveGoogleIdentityAction $resolveIdentity,
        private readonly GoogleSsoAuditLogger $auditLogger,
    ) {}

    public function execute(string $accessToken, ?string $deviceName = null): GoogleSsoResult
    {
        try {
            $googleUser = $this->client->userFromToken($accessToken);
        } catch (Exception $exception) {
            $this->auditLogger->exchangeFailed('token', $exception->getMessage());

            return GoogleSsoResult::error(
                'GOOGLE_TOKEN_INVALID',
                __('errors.GOOGLE_TOKEN_INVALID'),
                422,
            );
        }

        $resolved = $this->resolveIdentity->execute(
            googleUser: $googleUser,
            flow: 'token',
            rememberCookies: [],
            deviceName: $deviceName,
            allowDemoProvisioning: false,
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
            return GoogleSsoResult::error(
                'EMPLOYEE_NOT_FOUND',
                __('errors.GOOGLE_ACCOUNT_NOT_FOUND'),
                401,
            );
        }

        if ($resolved['mfa_challenge'] !== null) {
            return GoogleSsoResult::mfaChallenge(
                $resolved['mfa_challenge']['token'],
                $resolved['mfa_challenge']['expires_in'],
            );
        }

        $token = $employee->createToken($deviceName ?? 'google-mobile');

        return GoogleSsoResult::authenticated($employee, $token->plainTextToken);
    }
}
