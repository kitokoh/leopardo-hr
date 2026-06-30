<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\DTOs;

final class ProvisionCompanyDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $plan,
        public readonly string $country,
        public readonly ?int    $trialDays = 14,
        public readonly ?string $adminPassword = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:          $data['name'],
            email:         $data['email'],
            plan:          $data['plan'],
            country:       $data['country'],
            trialDays:     (int) ($data['trial_days'] ?? 14),
            adminPassword: $data['admin_password'] ?? null,
        );
    }
}
