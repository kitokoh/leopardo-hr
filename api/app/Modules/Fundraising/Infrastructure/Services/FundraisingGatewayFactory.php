<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Domain\Contracts\FundraisingGatewayInterface;
use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoney\CinetPayGateway;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoney\PayDunyaGateway;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoney\PvitGateway;
use InvalidArgumentException;

/**
 * Factory des passerelles de paiement de contributions (verticale
 * FUNDRAISING, spec §4.2) : résout la méthode de paiement vers son driver.
 *
 * - `card` → StripeContributionGateway ;
 * - `mobile_money` → provider choisi par `fundraising.mobile_money.provider`
 *   (FUND-101) : `sandbox` (MobileMoneyGateway, défaut), `cinetpay`,
 *   `paydunya` ou `pvit` — provider inconnu ⇒ exception (fail-closed,
 *   jamais de repli silencieux vers le sandbox) ;
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
            ContributionMethod::MOBILE_MONEY => $this->mobileMoneyGateway(),
            ContributionMethod::CASH, ContributionMethod::BANK_TRANSFER => new ManualGateway,
        };
    }

    /**
     * Résolution par nom de provider (webhooks `/webhooks/fundraising/{provider}`).
     */
    public function forProvider(string $provider): FundraisingGatewayInterface
    {
        return match ($provider) {
            'stripe' => $this->forMethod(ContributionMethod::CARD),
            'mobile_money' => $this->mobileMoneyGateway(),
            'cinetpay' => new CinetPayGateway($this->mobileMoneyProviderConfig('cinetpay')),
            'paydunya' => new PayDunyaGateway($this->mobileMoneyProviderConfig('paydunya')),
            'pvit' => new PvitGateway($this->mobileMoneyProviderConfig('pvit')),
            default => throw new InvalidArgumentException('Unknown fundraising payment provider: '.$provider),
        };
    }

    /**
     * Passerelle mobile money configurée (FUND-101) : `sandbox` par défaut,
     * sinon l'adaptateur production désigné par
     * `fundraising.mobile_money.provider`.
     */
    private function mobileMoneyGateway(): FundraisingGatewayInterface
    {
        $config = is_array($this->config['mobile_money'] ?? null) ? $this->config['mobile_money'] : [];
        $provider = (string) ($config['provider'] ?? 'sandbox');

        return match ($provider) {
            'sandbox' => new MobileMoneyGateway($config),
            'cinetpay' => new CinetPayGateway($this->mobileMoneyProviderConfig('cinetpay')),
            'paydunya' => new PayDunyaGateway($this->mobileMoneyProviderConfig('paydunya')),
            'pvit' => new PvitGateway($this->mobileMoneyProviderConfig('pvit')),
            default => throw new InvalidArgumentException('Unknown mobile money provider: '.$provider),
        };
    }

    /**
     * Sous-configuration d'un provider mobile money (bloc
     * `fundraising.mobile_money.{provider}`), vide si absente — l'adaptateur
     * reste alors fail-closed (`isConfigured() === false`).
     *
     * @return array<string, mixed>
     */
    private function mobileMoneyProviderConfig(string $provider): array
    {
        $config = is_array($this->config['mobile_money'] ?? null) ? $this->config['mobile_money'] : [];
        $providerConfig = $config[$provider] ?? null;

        return is_array($providerConfig) ? $providerConfig : [];
    }
}
