<?php

declare(strict_types=1);

namespace App\Services\SSO;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SSOService
{
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

        $configData = is_string($row->config) ? json_decode($row->config, true) : (array) $row->config;

        return [
            'enabled' => true,
            'provider' => $row->provider,
            'config' => SSOProviderConfig::fromArray([
                'provider' => $row->provider,
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

        DB::table('company_sso_configs')->updateOrInsert(
            ['company_id' => $companyId],
            [
                'provider' => $provider,
                'config' => json_encode($config->toArray()),
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ],
        );

        Log::info('SSO configured', [
            'company_id' => $companyId,
            'provider' => $provider,
        ]);

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

        // Stub: in production, decode and validate SAML assertion
        // using the company's IdP certificate
        Log::info('SAML response received', [
            'company_id' => $companyId,
            'response_length' => strlen($samlResponse),
        ]);

        return [
            'user_email' => '',
            'attributes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $tokenData
     * @return array{user_email: string, claims: array<string, mixed>}
     */
    public function handleOIDCCallback(string $companyId, array $tokenData): array
    {
        $sso = $this->getCompanySSO($companyId);

        if (! $sso['enabled'] || $sso['provider'] !== 'oidc') {
            throw new \RuntimeException('OIDC SSO not configured for this company');
        }

        // Stub: in production, validate ID token, exchange code for tokens
        Log::info('OIDC callback received', [
            'company_id' => $companyId,
        ]);

        return [
            'user_email' => '',
            'claims' => [],
        ];
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
                'description' => 'OAuth 2.0 + OpenID Connect — compatible Azure AD, Keycloak, Auth0, Google',
                'protocols' => ['OAuth 2.0', 'OpenID Connect 1.0'],
            ],
        ];
    }
}
