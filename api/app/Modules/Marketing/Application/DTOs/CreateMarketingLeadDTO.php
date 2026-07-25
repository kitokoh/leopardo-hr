<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\DTOs;

final class CreateMarketingLeadDTO
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $type,
        public readonly string $email,
        public readonly string $locale,
        public readonly ?string $country,
        public readonly ?string $page,
        public readonly ?string $source,
        public readonly ?string $campaign,
        public readonly ?string $ip,
        public readonly ?string $referrer,
        public readonly ?array $payload,
        public readonly bool $crmForwarded,
        public readonly bool $emailForwarded,
        public readonly ?string $capturedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) $data['external_id'],
            type: (string) $data['type'],
            email: (string) $data['email'],
            locale: (string) ($data['locale'] ?? 'fr'),
            country: isset($data['country']) ? (string) $data['country'] : null,
            page: isset($data['page']) ? (string) $data['page'] : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            campaign: isset($data['campaign']) ? (string) $data['campaign'] : null,
            ip: isset($data['ip']) ? (string) $data['ip'] : null,
            referrer: isset($data['referrer']) ? (string) $data['referrer'] : null,
            payload: $data['payload'] ?? null,
            crmForwarded: (bool) ($data['crm_forwarded'] ?? false),
            emailForwarded: (bool) ($data['email_forwarded'] ?? false),
            capturedAt: isset($data['captured_at']) ? (string) $data['captured_at'] : null,
        );
    }
}
