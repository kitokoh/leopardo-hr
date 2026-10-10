<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Domain\Contracts\FundraisingGatewayInterface;
use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use InvalidArgumentException;

/**
 * Factory des passerelles de paiement de contributions (verticale
 * FUNDRAISING, spec §4.2) : résout la méthode de paiement vers son driver.
 *
 * - `card` → StripeContributionGateway ;
 * - `mobile_money` → MobileMoneyGateway (agrégateur config-driven) ;
 * - `cash` / `bank_transfer` → ManualGateway.
 */
final class FundraisingGatewayFactory
{
    /**
     * @param  array<string, mixed>  $config  config/fundraising.php
     */
    public function __construct(private readonly array $config) {}

    public function forMethod(ContributionMethod $method): FundraisingGatewayInterface
    {
        return match ($method) {
            ContributionMethod::CARD => new StripeContributionGateway(
                is_array($this->config['stripe'] ?? null) ? $this->config['stripe'] : []
            ),
            ContributionMethod::MOBILE_MONEY => new MobileMoneyGateway(
                is_array($this->config['mobile_money'] ?? null) ? $this->config['mobile_money'] : []
            ),
            ContributionMethod::CASH, ContributionMethod::BANK_TRANSFER => new ManualGateway(),
        };
    }

    /**
     * Résolution par nom de provider (webhooks `/webhooks/fundraising/{provider}`).
     */
    public function forProvider(string $provider): FundraisingGatewayInterface
    {
        return match ($provider) {
            'stripe' => $this->forMethod(ContributionMethod::CARD),
            'mobile_money' => $this->forMethod(ContributionMethod::MOBILE_MONEY),
            default => throw new InvalidArgumentException('Unknown fundraising payment provider: '.$provider),
        };
    }
}
