<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\DTOs;

final class ConnectSocialAccountDTO
{
    public function __construct(
        public readonly string $companyId,
        public readonly ?int $createdBy,
        public readonly string $displayName,
        public readonly string $provider = 'ayrshare',
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (string) $data['company_id'],
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            displayName: (string) ($data['display_name'] ?? ''),
            provider: (string) ($data['provider'] ?? 'ayrshare'),
        );
    }
}
