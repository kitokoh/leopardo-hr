<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application\DTOs;

final class CreatePartnerDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $company = null,
        public readonly ?string $referralCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:         $data['name'],
            email:        $data['email'],
            company:      $data['company'] ?? null,
            referralCode: $data['referral_code'] ?? null,
        );
    }
}
