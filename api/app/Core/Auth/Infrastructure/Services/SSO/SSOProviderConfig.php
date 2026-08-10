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
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Audit #1694 : le client_secret ne doit JAMAIS être renvoyé au
        // client (ni ré-échappé) — il est chiffré au repos et restitué
        // uniquement côté serveur pour la future validation.
        return [
            'provider' => $this->provider,
            'entity_id' => $this->entityId,
            'sso_url' => $this->ssoUrl,
            'slo_url' => $this->sloUrl,
            'certificate' => $this->certificate,
            'client_id' => $this->clientId,
            'name_id_format' => $this->nameIdFormat,
            'metadata' => $this->metadata,
        ];
    }
}
