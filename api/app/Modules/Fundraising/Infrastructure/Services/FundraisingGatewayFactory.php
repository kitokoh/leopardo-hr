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
 * - `mobile_money` → l'adaptateur désigné par
 *   `fundraising.mobile_money.provider` : `cinetpay`, `paydunya`, `pvit`
 *   (adaptateurs de production FUND-101), sinon MobileMoneyGateway
 *   (agrégateur sandbox config-driven) ;
 * - `cash` / `bank_transfer` → ManualGateway.
 *
 * FUND-101 (#9098) — les trois adaptateurs de production existaient depuis
 * #8455 mais n'étaient atteignables par AUCUNE route : la factory ne les
 * construisait pas et `forProvider()` rejetait leur nom. Le webhook public
 * `/webhooks/fundraising/{provider}` répondait donc 404 à un IPN
 * CinetPay/PayDunya/PVIT réel, quelles que soient les clés posées.
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
            'mobile_money' => $this->forMethod(ContributionMethod::MOBILE_MONEY),
            'cinetpay' => new CinetPayGateway($this->providerConfig('cinetpay')),
            'paydunya' => new PayDunyaGateway($this->providerConfig('paydunya')),
            'pvit' => new PvitGateway($this->providerConfig('pvit')),
            default => throw new InvalidArgumentException('Unknown fundraising payment provider: '.$provider),
        };
    }

    /**
     * Adaptateur mobile money désigné par `provider`, ou l'agrégateur
     * sandbox si aucun n'est configuré.
     *
     * Un `provider` renseigné mais inconnu lève : une faute de frappe en
     * production doit faire échouer l'initiation bruyamment, jamais retomber
     * silencieusement sur le sandbox (qui confirmerait alors tous les
     * paiements — `verify()` y renvoie `paid: true`).
     */
    private function mobileMoneyGateway(): FundraisingGatewayInterface
    {
        $provider = strtolower(trim((string) ($this->mobileMoneyConfig()['provider'] ?? '')));

        return match ($provider) {
            '' => new MobileMoneyGateway($this->mobileMoneyConfig()),
            'cinetpay' => new CinetPayGateway($this->providerConfig('cinetpay')),
            'paydunya' => new PayDunyaGateway($this->providerConfig('paydunya')),
            'pvit' => new PvitGateway($this->providerConfig('pvit')),
            default => throw new InvalidArgumentException('Unknown fundraising mobile money provider: '.$provider),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mobileMoneyConfig(): array
    {
        return is_array($this->config['mobile_money'] ?? null) ? $this->config['mobile_money'] : [];
    }

    /**
     * Bloc d'identifiants d'un adaptateur (`mobile_money.providers.{name}`).
     *
     * @return array<string, mixed>
     */
    private function providerConfig(string $provider): array
    {
        $providers = $this->mobileMoneyConfig()['providers'] ?? null;

        if (! is_array($providers)) {
            return [];
        }

        $block = $providers[$provider] ?? null;

        return is_array($block) ? $block : [];
    }
}
