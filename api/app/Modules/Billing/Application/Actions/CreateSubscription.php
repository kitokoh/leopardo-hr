<?php

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Infrastructure\Services\StripeService;

class CreateSubscription
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    public function handle(string $companyId, string $planId, ?string $paymentMethodId = null): Subscription
    {
        return $this->stripeService->createSubscription($companyId, $planId, $paymentMethodId);
    }
}
