<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Rules\NotPrivateUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Flux OpenID Connect complet (issue #2231/#2197/#2251) :
 *
 * 1. `buildAuthorizeUrl()` — génère state+nonce (cache 10 min), construit
 *    l'URL d'autorisation de l'IdP ;
 * 2. `complete()` — callback : vérifie state, échange le code contre les
 *    tokens, valide l'id_token (signature JWKS + iss/aud/exp/nonce), résout
 *    l'employé par email et émet un token Sanctum.
 *
 * SAML reste en 501 (validation OneLogin non implémentée) — ce service ne
 * couvre que le protocole OIDC.
 */
final class OidcFlowService
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly SSOService $ssoService,
        private readonly OidcIdTokenValidator $idTokenValidator,
        private readonly AuthService $authService,
    ) {}

    /**
     * @return array{authorize_url: string}
     */
    public function buildAuthorizeUrl(string $companyId): array
    {
        $config = $this->oidcConfig($companyId);

        $state = Str::random(32);
        $nonce = Str::random(32);

        Cache::put(
            $this->stateCacheKey($companyId, $state),
            ['nonce' => $nonce],
            self::STATE_TTL_SECONDS
        );

        $params = [
            'response_type' => 'code',
            'client_id' => $config->clientId,
            'redirect_uri' => $config->redirectUri,
            'scope' => $config->scopes,
            'state' => $state,
            'nonce' => $nonce,
        ];

        $separator = str_contains((string) $config->authorizeUrl, '?') ? '&' : '?';

        return [
            'authorize_url' => (string) $config->authorizeUrl.$separator.http_build_query($params),
        ];
    }

    /**
     * @param  array<string, mixed>  $callbackData  code + state (+ id_token direct optionnel)
     * @return array{employee: array<string, mixed>, token: string, token_type: string, token_expires_at: ?string}
     */
    public function complete(string $companyId, array $callbackData): array
    {
        $config = $this->oidcConfig($companyId);

        $state = (string) ($callbackData['state'] ?? '');
        $code = (string) ($callbackData['code'] ?? '');

        $nonce = $this->consumeState($companyId, $state);
        if ($nonce === null) {
            throw new \RuntimeException('SSO_STATE_INVALID: état OIDC inconnu, expiré ou déjà consommé.');
        }

        $idToken = is_string($callbackData['id_token'] ?? null) ? (string) $callbackData['id_token'] : '';

        if ($idToken === '') {
            if ($code === '') {
                throw new \RuntimeException('SSO_MISSING_CODE: code ou id_token manquant.');
            }

            $idToken = $this->exchangeCodeForIdToken($config, $code);
        }

        $claims = $this->idTokenValidator->validate($idToken, [
            'issuer' => (string) $config->issuer,
            'client_id' => (string) $config->clientId,
            'nonce' => $nonce,
            'jwks_uri' => (string) $config->jwksUri,
        ]);

        $email = $this->extractEmail($claims);

        try {
            $result = $this->authService->loginViaEmail($email, 'sso-oidc');
        } catch (\App\Exceptions\InvalidCredentialsException $e) {
            throw new \RuntimeException('SSO_USER_NOT_FOUND: aucun employé actif avec cet email ('.$email.').');
        }

        $employee = $result['employee'];
        if ((string) $employee->company_id !== $companyId) {
            throw new \RuntimeException('SSO_TENANT_MISMATCH: l\'email SSO appartient à une autre entreprise.');
        }

        return [
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'manager_role' => $employee->manager_role,
            ],
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'token_expires_at' => $result['token_expires_at'],
        ];
    }

    private function oidcConfig(string $companyId): SSOProviderConfig
    {
        $sso = $this->ssoService->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'oidc' || ! $sso['config'] instanceof SSOProviderConfig) {
            throw new \RuntimeException('OIDC SSO non configuré pour cette entreprise.');
        }

        if (! $sso['config']->isOidcFlowReady()) {
            throw new \RuntimeException('OIDC SSO incomplet : issuer, authorize_url, token_url, jwks_uri, redirect_uri et client_id requis.');
        }

        foreach (['authorizeUrl', 'tokenUrl', 'jwksUri'] as $field) {
            $url = $sso['config']->{$field};
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            if (! is_string($host) || ! NotPrivateUrl::isPublicHost($host)) {
                throw new \RuntimeException('OIDC endpoint refusé : hôte privé, réservé ou non résolvable.');
            }
        }

        return $sso['config'];
    }

    private function stateCacheKey(string $companyId, string $state): string
    {
        return 'sso:oidc:state:'.$companyId.':'.$state;
    }

    private function consumeState(string $companyId, string $state): ?string
    {
        if ($state === '') {
            return null;
        }

        $key = $this->stateCacheKey($companyId, $state);

        /** @var array{nonce?: string}|null $entry */
        $entry = Cache::get($key);

        // Le docblock ci-dessus garantit nonce: string quand présent ; un
        // nonce vide = état introuvable (expiré/consommé).
        if (! is_array($entry) || empty($entry['nonce'])) {
            return null;
        }

        Cache::forget($key);

        return $entry['nonce'];
    }

    private function exchangeCodeForIdToken(SSOProviderConfig $config, string $code): string
    {
        try {
            $response = Http::asForm()->timeout(15)->acceptJson()->post((string) $config->tokenUrl, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => (string) $config->redirectUri,
                'client_id' => (string) $config->clientId,
                'client_secret' => (string) ($config->clientSecret ?? ''),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('SSO_TOKEN_EXCHANGE_FAILED: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new \RuntimeException('SSO_TOKEN_EXCHANGE_FAILED: l\'IdP a refusé l\'échange du code ('.$response->status().').');
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new \RuntimeException('SSO_NO_ID_TOKEN: la réponse de l\'IdP ne contient pas d\'id_token.');
        }

        return $idToken;
    }

    /**
     * #5580 — fail-closed : seuls les emails vérifiés par l'IdP peuvent
     * matcher un compte employé (parité portail client, GoogleIdentityVerifier).
     *
     * Preuve de vérification acceptée :
     * - claim `email_verified === true` (standard OIDC, Google) ;
     * - ou `email_verified_at` (horodatage non vide et valide) pour les IdP
     *   qui ne publient pas le booléen.
     *
     * Sans preuve de vérification, un id_token ne peut pas être utilisé pour
     * s'authentifier (SSO_EMAIL_NOT_VERIFIED) — y compris quand seul
     * `preferred_username`/`upn` est présent.
     *
     * @param  array<string, mixed>  $claims
     */
    private function emailIsVerified(array $claims): bool
    {
        if (($claims['email_verified'] ?? false) === true) {
            return true;
        }

        $verifiedAt = $claims['email_verified_at'] ?? null;

        return is_string($verifiedAt) && $verifiedAt !== '' && strtotime($verifiedAt) !== false;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function extractEmail(array $claims): string
    {
        foreach (['email', 'preferred_username', 'upn'] as $field) {
            if (isset($claims[$field]) && is_string($claims[$field]) && $claims[$field] !== '') {
                if (! $this->emailIsVerified($claims)) {
                    throw new \RuntimeException('SSO_EMAIL_NOT_VERIFIED: l\'id_token ne prouve pas la vérification de l\'email (email_verified/email_verified_at requis).');
                }

                return strtolower(trim($claims[$field]));
            }
        }

        throw new \RuntimeException('SSO_EMAIL_MISSING: l\'id_token ne contient ni email ni preferred_username.');
    }
}
