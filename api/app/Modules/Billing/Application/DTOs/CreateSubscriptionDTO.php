<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

final class CreateSubscriptionDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly string $plan,
        public readonly string $paymentMethod,
        public readonly ?string $stripeToken = null,
        public readonly ?int    $trialDays = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId:     (int) $data['company_id'],
            plan:          $data['plan'],
            paymentMethod: $data['payment_method'],
            stripeToken:   $data['stripe_token'] ?? null,
            trialDays:     isset($data['trial_days']) ? (int) $data['trial_days'] : null,
        );
    }
}
