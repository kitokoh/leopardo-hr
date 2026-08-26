<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SSOService
{
    /**
     * Champs sensibles chiffrés au repos (audit #1694).
     *
     * @var list<string>
     */
    private const SENSITIVE_FIELDS = ['certificate', 'client_secret'];

    /**
     * Retourne si la validation SSO est disponible pour un provider donné.
     *
     * - OIDC (#2231) : OidcFlowService est pleinement implémenté → true.
     * - SAML         : validation OneLogin non implémentée (audit #1694) → false.
     *
     * Issue #5614 : l'ancien VALIDATION_AVAILABLE = false bloquait aussi OIDC.
     */
    private function isValidationAvailableForProvider(string $provider): bool
    {
        return $provider === 'oidc';
    }

    /**
     * @return array{enabled: bool, provider: string|null, config: SSOProviderConfig|null, validation_available: bool}
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
                'validation_available' => false,
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
            // #5614 : true pour OIDC (OidcFlowService implémenté), false pour SAML.
            'validation_available' => $this->isValidationAvailableForProvider($provider),
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

        // Audit #1694 : la validation SAML n'est pas encore implémentée —
        // ne JAMAIS marquer une config SAML comme active (fausse garantie de
        // sécurité). Pour OIDC (issue #2231), la validation EST implémentée
        // (OidcFlowService) : la config devient active quand elle est complète.
        $canActivate = $config->provider === 'oidc' && $config->isOidcFlowReady();

        $payload = [
            'provider' => $provider,
            'config' => json_encode($stored),
            'is_active' => $canActivate,
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

        if ($canActivate) {
            Log::info('OIDC SSO configuré et actif (issue #2231/#5614)', [
                'company_id' => $companyId,
            ]);
        } else {
            // SAML : en attente de l'implémentation OneLogin/php-saml (audit #1694).
            Log::warning('SSO configuré mais inactif (SAML non implémenté ou config OIDC incomplète)', [
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
     * @param  array<string, mixed>  $tokenData
     * @return array{employee: array<string, mixed>, token: string, token_type: string, token_expires_at: string|null}
     */
    public function handleOIDCCallback(string $companyId, array $tokenData): array
    {
        $sso = $this->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'oidc') {
            throw new \RuntimeException('OIDC SSO not configured for this company');
        }

        // Issue #2231 : le flux OIDC complet (authorize + callback +
        // validation id_token) vit dans OidcFlowService — cette méthode est
        // conservée pour les appelants qui ne passent pas par le contrôleur.
        return app(OidcFlowService::class)->complete($companyId, $tokenData);
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
        } catch (Throwable $e) {
            // Valeur legacy stockée en clair avant l'audit #1694 : on conserve
            // le repli pour la migration, mais on le trace (#3243) pour ne
            // plus traiter silencieusement une config non déchiffrable.
            Log::warning('SSO secret non déchiffrable — repli valeur stockée', [
                'error' => $e->getMessage(),
            ]);

            return $value;
        }
    }
}
