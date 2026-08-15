<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

class SSOProviderConfig
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $entityId,
        public readonly string $ssoUrl,
        public readonly ?string $sloUrl,
        public readonly ?string $certificate,
        public readonly ?string $clientId = null,
        public readonly ?string $clientSecret = null,
        public readonly string $nameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        public readonly array $metadata = [],
        // OpenID Connect 1.0 fields (issue #2231) — nullable pour rester
        // compatibles avec les configs SAML existantes.
        public readonly ?string $issuer = null,
        public readonly ?string $authorizeUrl = null,
        public readonly ?string $tokenUrl = null,
        public readonly ?string $jwksUri = null,
        public readonly ?string $redirectUri = null,
        public readonly string $scopes = 'openid email profile',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) ($data['provider'] ?? 'saml'),
            entityId: (string) ($data['entity_id'] ?? ''),
            ssoUrl: (string) ($data['sso_url'] ?? ''),
            sloUrl: isset($data['slo_url']) ? (string) $data['slo_url'] : null,
            certificate: isset($data['certificate']) ? (string) $data['certificate'] : null,
            clientId: isset($data['client_id']) ? (string) $data['client_id'] : null,
            clientSecret: isset($data['client_secret']) ? (string) $data['client_secret'] : null,
            nameIdFormat: (string) ($data['name_id_format'] ?? 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
            metadata: (array) ($data['metadata'] ?? []),
            issuer: isset($data['issuer']) ? (string) $data['issuer'] : null,
            authorizeUrl: isset($data['authorize_url']) ? (string) $data['authorize_url'] : null,
            tokenUrl: isset($data['token_url']) ? (string) $data['token_url'] : null,
            jwksUri: isset($data['jwks_uri']) ? (string) $data['jwks_uri'] : null,
            redirectUri: isset($data['redirect_uri']) ? (string) $data['redirect_uri'] : null,
            scopes: (string) ($data['scopes'] ?? 'openid email profile'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Audit #1694 : le client_secret ne doit JAMAIS être renvoyé au
        // client (ni ré-échappé) — il est chiffré au repos et restitué
        // uniquement côté serveur pour la validation.
        return [
            'provider' => $this->provider,
            'entity_id' => $this->entityId,
            'sso_url' => $this->ssoUrl,
            'slo_url' => $this->sloUrl,
            'certificate' => $this->certificate,
            'client_id' => $this->clientId,
            'name_id_format' => $this->nameIdFormat,
            'metadata' => $this->metadata,
            'issuer' => $this->issuer,
            'authorize_url' => $this->authorizeUrl,
            'token_url' => $this->tokenUrl,
            'jwks_uri' => $this->jwksUri,
            'redirect_uri' => $this->redirectUri,
            'scopes' => $this->scopes,
        ];
    }

    /**
     * True quand la config OIDC est complète et utilisable (issue #2231) :
     * tous les champs requis par le flux authorize/callback sont présents.
     */
    public function isOidcFlowReady(): bool
    {
        if ($this->provider !== 'oidc') {
            return false;
        }

        return $this->clientId !== null
            && $this->clientId !== ''
            && $this->issuer !== null && $this->issuer !== ''
            && $this->authorizeUrl !== null && $this->authorizeUrl !== ''
            && $this->tokenUrl !== null && $this->tokenUrl !== ''
            && $this->jwksUri !== null && $this->jwksUri !== ''
            && $this->redirectUri !== null && $this->redirectUri !== '';
    }
}
