<?php

namespace App\Modules\Billing\Application\Actions;

use App\Models\Company;
use App\Modules\Billing\Infrastructure\Services\StripeService;

class CreateSubscription
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * @return array{url: string, session_id: string}
     */
    public function handle(Company $company, string $plan, string $successUrl, string $cancelUrl): array
    {
        return $this->stripeService->createCheckoutSession($company, $plan, $successUrl, $cancelUrl);
    }
}
