<?php

declare(strict_types=1);

namespace App\Services\SSO;

class SSOProviderConfig
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $entityId,
        public readonly string $ssoUrl,
        public readonly ?string $sloUrl,
        public readonly ?string $certificate,
        public readonly string $nameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        public readonly array $metadata = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) ($data['provider'] ?? 'saml'),
            entityId: (string) ($data['entity_id'] ?? ''),
            ssoUrl: (string) ($data['sso_url'] ?? ''),
            sloUrl: isset($data['slo_url']) ? (string) $data['slo_url'] : null,
            certificate: isset($data['certificate']) ? (string) $data['certificate'] : null,
            nameIdFormat: (string) ($data['name_id_format'] ?? 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'entity_id' => $this->entityId,
            'sso_url' => $this->ssoUrl,
            'slo_url' => $this->sloUrl,
            'certificate' => $this->certificate,
            'name_id_format' => $this->nameIdFormat,
            'metadata' => $this->metadata,
        ];
    }
}
