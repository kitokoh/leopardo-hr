<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flow OAuth 2.0 Google COTE SERVEUR pour le module Communication
 * (BC-29, R1 #7686 — spec MODULE_COMMUNICATION_EMAIL_IA.md §3.1).
 *
 * Contrats de securite :
 * - `access_type=offline` + `prompt=consent` -> Google renvoie TOUJOURS un
 *   refresh_token a la connexion (l'existant Calendar recevait le token du
 *   client sans refresh — c'est le manque que R1 comble).
 * - Scopes minimaux (`openid email` pour identifier la boite,
 *   `gmail.readonly` pour R2) — les scopes d'ecriture (send/modify)
 *   n'arrivent que si l'utilisateur active les reponses/relances (R4/R5,
 *   scopes incrementaux via `include_granted_scopes`).
 * - Les tokens ne transitent JAMAIS par les logs (seuls des codes d'erreur
 *   machine sont journalises) ; leur stockage passe par les casts
 *   `encrypted` du modele.
 * - Refresh transparent (`ensureValidAccessToken`) : marge 60 s, un
 *   `invalid_grant` (revocation cote Google, mot de passe change) marque la
 *   ligne `error` et purge l'access token — jamais de token mort reutilise.
 * - Revocation propre (`revoke`) : POST /revoke chez Google (best effort,
 *   un token deja invalide ne bloque pas la deconnexion) puis PURGE locale
 *   des deux tokens et statut `revoked`.
 *
 * Client id/secret : variables d'env `GOOGLE_CLIENT_ID` /
 * `GOOGLE_CLIENT_SECRET` (config/services.php) — aucun secret committe.
 * Les tests Feature mockent ces endpoints via `Http::fake` (aucun appel
 * reseau reel).
 */
class GoogleGmailOAuthService
{
    public const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public const REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

    public const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    /**
     * Scopes R1 : identifier la boite (openid/email) + lecture Gmail.
     * `gmail.send` / `gmail.modify` arrivent en R4/R5 (incremental).
     */
    public const DEFAULT_SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.readonly',
    ];

    /**
     * Les trois variables d'env necessaires au flow (pattern #5170).
     */
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.communication_redirect'));
    }

    /**
     * URL de consentement Google pour CE state (anti-CSRF, une seule fois).
     *
     * @param  list<string>  $scopes
     */
    public function authorizationUrl(string $state, array $scopes = self::DEFAULT_SCOPES): string
    {
        $query = http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => (string) config('services.google.communication_redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            // offline + consent : indispensables pour obtenir un refresh_token.
            'access_type' => 'offline',
            'prompt' => 'consent',
            // Scopes incrementaux (R4/R5) : les scopes deja accordes restent.
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    /**
     * Echange le code d'autorisation contre les tokens.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, scopes: list<string>}|null
     */
    public function exchangeCode(string $code): ?array
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => (string) config('services.google.communication_redirect'),
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            // Jamais le body complet en logs (peut contenir des tokens partiels).
            Log::warning('communication.google.code_exchange_failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return null;
        }

        $scope = $response->json('scope');

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => is_string($response->json('refresh_token'))
                ? (string) $response->json('refresh_token')
                : null,
            'expires_in' => (int) ($response->json('expires_in') ?? 3600),
            'scopes' => is_string($scope) && $scope !== '' ? explode(' ', $scope) : [],
        ];
    }

    /**
     * Adresse de la boite connectee via userinfo (scope `email` seul —
     * minimisation : on ne lit ni le nom, ni la photo, ni le reste du profil).
     */
    public function fetchAccountEmail(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)->get(self::USERINFO_ENDPOINT);

        $email = $response->json('email');

        return $response->successful() && is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * Access token valide, rafraichi de facon TRANSPARENTE si necessaire
     * (marge 60 s). Null si l'integration n'est plus utilisable (revoquee,
     * en erreur, refresh refuse par Google).
     */
    public function ensureValidAccessToken(CommunicationIntegration $integration): ?string
    {
        if (! $integration->isActive()) {
            return null;
        }

        if (! $integration->accessTokenNeedsRefresh()) {
            return $integration->access_token;
        }

        return $this->refreshAccessToken($integration);
    }

    /**
     * Rafraichit l'access token avec le refresh_token stocke (chiffre).
     * `invalid_grant` = acces retire cote Google -> statut `error`, access
     * token purge (le refresh_token est conserve pour diagnostic mais la
     * ligne n'est plus utilisable tant que l'utilisateur ne se reconnecte pas).
     */
    public function refreshAccessToken(CommunicationIntegration $integration): ?string
    {
        if ($integration->refresh_token === null) {
            $integration->forceFill([
                'status' => CommunicationIntegration::STATUS_ERROR,
                'last_error' => 'missing_refresh_token',
            ])->save();

            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $integration->refresh_token,
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            $errorCode = is_string($response->json('error')) ? (string) $response->json('error') : 'refresh_failed';

            Log::warning('communication.google.token_refresh_failed', [
                'integration_id' => $integration->id,
                'status' => $response->status(),
                'error' => $errorCode,
            ]);

            $integration->forceFill([
                'status' => CommunicationIntegration::STATUS_ERROR,
                'access_token' => null,
                'expires_at' => null,
                'last_error' => mb_substr($errorCode, 0, 100),
            ])->save();

            return null;
        }

        $accessToken = (string) $response->json('access_token');

        $integration->forceFill([
            'access_token' => $accessToken,
            'expires_at' => now()->addSeconds((int) ($response->json('expires_in') ?? 3600)),
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'last_error' => null,
        ])->save();

        return $accessToken;
    }

    /**
     * Deconnexion PROPRE : revocation cote Google (best effort — revoquer le
     * refresh_token invalide toute la grappe de tokens), puis purge locale
     * inconditionnelle des deux tokens et statut `revoked`. Meme si Google
     * repond une erreur (token deja mort), l'utilisateur repart proprement.
     */
    public function revoke(CommunicationIntegration $integration): void
    {
        $tokenToRevoke = $integration->refresh_token ?? $integration->access_token;

        if ($tokenToRevoke !== null) {
            try {
                $response = Http::asForm()->post(self::REVOKE_ENDPOINT, [
                    'token' => $tokenToRevoke,
                ]);

                if ($response->failed()) {
                    Log::info('communication.google.revoke_non_blocking_failure', [
                        'integration_id' => $integration->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::warning('communication.google.revoke_unreachable', [
                    'integration_id' => $integration->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $integration->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'scopes' => null,
            'status' => CommunicationIntegration::STATUS_REVOKED,
            'revoked_at' => now(),
            'last_error' => null,
        ])->save();
    }
}
