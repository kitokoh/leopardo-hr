<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SSOService
{
    public function __construct(
        private readonly OidcJwtValidator $oidcValidator,
    ) {}

    /**
     * Champs sensibles chiffrés au repos (audit #1694).
     *
     * @var list<string>
     */
    private const SENSITIVE_FIELDS = ['certificate', 'client_secret'];

    /**
     * @return array{enabled: bool, provider: string|null, config: SSOProviderConfig|null}
     */
    public function getCompanySSO(string $companyId): array
    {
        $row = DB::table('company_sso_configs')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $row) {
            return [
                'enabled' => false,
                'provider' => null,
                'config' => null,
            ];
        }

        /** @var array<string, mixed> $configData */
        $configData = is_string($row->config) ? json_decode((string) $row->config, true) : (array) $row->config;

        // Audit #1694 : déchiffrement des champs sensibles, avec repli
        // rétrocompatible sur les anciennes valeurs stockées en clair.
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($configData[$field]) && is_string($configData[$field])) {
                $configData[$field] = $this->decryptField($configData[$field]);
            }
        }

        $provider = is_string($row->provider) ? $row->provider : '';

        return [
            'enabled' => true,
            'provider' => $provider,
            'config' => SSOProviderConfig::fromArray([
                'provider' => $provider,
                ...$configData,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $configData
     */
    public function configureSSO(string $companyId, string $provider, array $configData): SSOProviderConfig
    {
        $config = SSOProviderConfig::fromArray([
            'provider' => $provider,
            ...$configData,
        ]);

        $existingRow = DB::table('company_sso_configs')
            ->where('company_id', $companyId)
            ->first();

        $stored = $config->toArray();

        // Audit #1694 : le client_secret est exclu de toArray() (jamais
        // renvoyé au client) mais DOIT être persisté — chiffré au repos.
        if ($config->clientSecret !== null) {
            $stored['client_secret'] = $config->clientSecret;
        } elseif ($existingRow !== null) {
            // Mise à jour sans client_secret : préserver le secret déjà
            // stocké (une réécriture de config ne doit pas le perdre).
            /** @var array<string, mixed> $existingData */
            $existingData = is_string($existingRow->config)
                ? json_decode((string) $existingRow->config, true)
                : (array) $existingRow->config;
            if (isset($existingData['client_secret']) && is_string($existingData['client_secret'])) {
                $stored['client_secret'] = $this->decryptField($existingData['client_secret']);
            }
        }

        // Audit #1694 : chiffrement des secrets IdP avant persistance.
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (! empty($stored[$field]) && is_string($stored[$field])) {
                $stored[$field] = Crypt::encryptString($stored[$field]);
            }
        }

        // Audit #1694 : la validation des assertions SAML n'est pas encore
        // implémentée — ne JAMAIS marquer une config SAML comme active.
        // QA #2231 : une config OIDC COMPLÈTE (entity_id, sso_url, client_id,
        // client_secret) est activable — la validation de l'ID token est
        // désormais implémentée (OidcJwtValidator).
        $oidcComplete = $provider === 'oidc'
            && $config->entityId !== ''
            && $config->ssoUrl !== ''
            && $config->clientId !== null
            && $config->clientSecret !== null;

        $payload = [
            'provider' => $provider,
            'config' => json_encode($stored),
            'is_active' => $oidcComplete,
            'updated_at' => now(),
        ];

        if ($existingRow !== null) {
            DB::table('company_sso_configs')
                ->where('company_id', $companyId)
                ->update($payload);
        } else {
            DB::table('company_sso_configs')->insert([
                'company_id' => $companyId,
                ...$payload,
                'created_at' => now(),
            ]);
        }

        if ($oidcComplete) {
            Log::info('OIDC SSO configured and activated', [
                'company_id' => $companyId,
            ]);
        } else {
            Log::warning('SSO configured but kept inactive (SAML non validé — audit #1694, ou config OIDC incomplète)', [
                'company_id' => $companyId,
                'provider' => $provider,
            ]);
        }

        return $config;
    }

    public function disableSSO(string $companyId): void
    {
        DB::table('company_sso_configs')
            ->where('company_id', $companyId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        Log::info('SSO disabled', ['company_id' => $companyId]);
    }

    /**
     * @return array{user_email: string, attributes: array<string, mixed>}
     */
    public function handleSAMLResponse(string $companyId, string $samlResponse): array
    {
        $sso = $this->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'saml') {
            throw new \RuntimeException('SAML SSO not configured for this company');
        }

        // Audit #1694 : l'ancien stub retournait un succès vide sans valider
        // l'assertion — fausse garantie de sécurité. Refus explicite tant
        // que la validation (OneLogin/php-saml) n'est pas implémentée.
        throw new SSOValidationNotImplementedException(
            'La validation des assertions SAML n\'est pas encore implémentée — connexion refusée.'
        );
    }

    /**
     * Valide l'ID token OIDC (iss/aud/exp + signature JWKS), vérifie l'état
     * CSRF (state) et retourne l'email de l'utilisateur + les claims.
     *
     * @param  array<string, mixed>  $tokenData  (id_token, state)
     * @return array{user_email: string, claims: array<string, mixed>}
     */
    public function handleOIDCCallback(string $companyId, array $tokenData): array
    {
        $sso = $this->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'oidc' || ! $sso['config'] instanceof SSOProviderConfig) {
            throw new \RuntimeException('OIDC SSO not configured for this company');
        }

        $config = $sso['config'];
        $idToken = (string) ($tokenData['id_token'] ?? '');
        $state = (string) ($tokenData['state'] ?? '');

        if ($idToken === '') {
            throw new \RuntimeException('Missing id_token');
        }

        // Anti-CSRF : le state émis à l'authorize doit matcher.
        if ($state !== '') {
            $expectedState = Cache::get($this->stateCacheKey($companyId));
            if ($expectedState === null || ! hash_equals((string) $expectedState, $state)) {
                throw new \RuntimeException('OIDC state mismatch');
            }
            Cache::forget($this->stateCacheKey($companyId));
        }

        $discovered = $this->oidcValidator->discover($config->entityId);

        /** @var array{issuer: string, audience: string, client_secret?: string|null, jwks_uri?: string|null} $expected */
        $expected = [
            'issuer' => $discovered['issuer'],
            'audience' => (string) ($config->clientId ?? ''),
            'client_secret' => $config->clientSecret,
            'jwks_uri' => $discovered['jwks_uri'],
        ];

        $claims = $this->oidcValidator->validateIdToken($idToken, $expected);

        $email = (string) ($claims['email'] ?? '');
        if ($email === '') {
            throw new \RuntimeException('OIDC ID token does not carry an email claim');
        }

        return [
            'user_email' => mb_strtolower($email),
            'claims' => $claims,
        ];
    }

    /**
     * Construit l'URL d'autorisation IdP (redirect + state en cache).
     *
     * @return array{url: string, state: string, nonce: string}
     */
    public function buildOidcAuthorizeUrl(string $companyId, string $redirectUri): array
    {
        $sso = $this->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'oidc' || ! $sso['config'] instanceof SSOProviderConfig) {
            throw new \RuntimeException('OIDC SSO not configured for this company');
        }

        $config = $sso['config'];
        $state = Str::random(40);
        $nonce = Str::random(40);

        Cache::put($this->stateCacheKey($companyId), $state, 600);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $config->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
        ]);

        return [
            'url' => $config->ssoUrl.(str_contains($config->ssoUrl, '?') ? '&' : '?').$params,
            'state' => $state,
            'nonce' => $nonce,
        ];
    }

    /**
     * Échange le code d'autorisation contre des tokens au token_endpoint
     * (découverte OIDC) et retourne la réponse brute.
     *
     * @return array<string, mixed>
     */
    public function exchangeOidcCode(string $companyId, string $code, string $redirectUri): array
    {
        $sso = $this->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'oidc' || ! $sso['config'] instanceof SSOProviderConfig) {
            throw new \RuntimeException('OIDC SSO not configured for this company');
        }

        $config = $sso['config'];
        $discovered = $this->oidcValidator->discover($config->entityId);

        $response = Http::timeout(15)->asForm()->post($discovered['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config->clientId,
            'client_secret' => $config->clientSecret,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OIDC token exchange failed: '.$response->body());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    private function stateCacheKey(string $companyId): string
    {
        return 'oidc_state_'.$companyId;
    }

    /**
     * @return list<array{provider: string, name: string, description: string, protocols: list<string>}>
     */
    public function getSupportedProviders(): array
    {
        return [
            [
                'provider' => 'saml',
                'name' => 'SAML 2.0',
                'description' => 'Security Assertion Markup Language — compatible Azure AD, Okta, OneLogin, Google Workspace',
                'protocols' => ['SAML 2.0'],
            ],
            [
                'provider' => 'oidc',
                'name' => 'OpenID Connect',
                'description' => 'OpenID Connect 1.0 — compatible Google, Microsoft Entra, Auth0, Keycloak',
                'protocols' => ['OIDC 1.0'],
            ],
        ];
    }

    private function decryptField(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Valeur legacy stockée en clair avant l'audit #1694.
            return $value;
        }
    }
}
