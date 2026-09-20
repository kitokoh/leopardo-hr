<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CardOnlinePaymentGateway;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;

/**
 * #7728 (BC-25 RESTAURANT / BC-21) — état de configuration de l'ENCAISSEMENT
 * EN LIGNE d'un restaurateur, dérivé de ses profils de paiement tenant
 * (contrat partagé #7727, garde d'isolation #5584 : jamais d'import direct de
 * Modules/Billing).
 *
 * Consommé par :
 * - la commande publique (choix du provider par défaut + fail-closed
 *   « paiement en ligne non configuré ») ;
 * - l'écran restaurateur (GET /restaurant/payments/configuration).
 *
 * Aucun secret n'est exposé : uniquement des booléens/état.
 */
final class RestaurantPaymentConfigurationService
{
    public const PROVIDER_MOBILE_MONEY = 'mobile_money';

    public function __construct(
        private readonly TenantPaymentProfileResolverInterface $tenantProfiles,
    ) {}

    /**
     * Providers EN LIGNE disponibles pour une compagnie, par ordre de
     * préférence (carte en ligne d'abord).
     *
     * @return list<string>
     */
    public function onlineProviders(string $companyId): array
    {
        $providers = [];

        if ($this->cardOnlineConfigured($companyId)) {
            $providers[] = CardOnlinePaymentGateway::PROVIDER_CODE;
        }

        if ($this->mobileMoneyConfigured($companyId)) {
            $providers[] = self::PROVIDER_MOBILE_MONEY;
        }

        return $providers;
    }

    public function isOnlineProvider(string $providerCode): bool
    {
        return in_array($providerCode, [CardOnlinePaymentGateway::PROVIDER_CODE, self::PROVIDER_MOBILE_MONEY], true);
    }

    public function providerConfigured(string $companyId, string $providerCode): bool
    {
        return match ($providerCode) {
            CardOnlinePaymentGateway::PROVIDER_CODE => $this->cardOnlineConfigured($companyId),
            self::PROVIDER_MOBILE_MONEY => $this->mobileMoneyConfigured($companyId),
            default => false,
        };
    }

    /**
     * Carte en ligne : profil `stripe_keys` ACTIF du tenant (fail-closed,
     * aucun fallback plateforme sur la surface publique restaurant).
     */
    public function cardOnlineConfigured(string $companyId): bool
    {
        return $this->tenantProfiles->stripeCredentialsForCompany($companyId) !== null;
    }

    /**
     * Mobile money : sandbox (historique) OU production feature-flaggée avec
     * profil `mobile_money` actif + endpoint provider configuré.
     */
    public function mobileMoneyConfigured(string $companyId): bool
    {
        if ((bool) config('restaurantmanager.mobile_money.sandbox', true)) {
            return true;
        }

        return (bool) config('restaurantmanager.mobile_money.production.enabled', false)
            && (string) config('restaurantmanager.mobile_money.production.initiate_url', '') !== ''
            && $this->tenantProfiles->activeMobileMoneyProfileForCompany($companyId) !== null;
    }

    /**
     * État complet pour l'écran restaurateur — aucun secret, uniquement des
     * indicateurs de configuration.
     *
     * @return array{
     *     online_enabled: bool,
     *     online_providers: list<string>,
     *     card_online: array{configured: bool},
     *     mobile_money: array{configured: bool, sandbox: bool, production_enabled: bool, profile_active: bool},
     *     pay_on_site: bool
     * }
     */
    public function statusForCompany(string $companyId): array
    {
        $providers = $this->onlineProviders($companyId);
        $sandbox = (bool) config('restaurantmanager.mobile_money.sandbox', true);

        return [
            'online_enabled' => $providers !== [],
            'online_providers' => $providers,
            'card_online' => [
                'configured' => $this->cardOnlineConfigured($companyId),
            ],
            'mobile_money' => [
                'configured' => $this->mobileMoneyConfigured($companyId),
                'sandbox' => $sandbox,
                'production_enabled' => (bool) config('restaurantmanager.mobile_money.production.enabled', false),
                'profile_active' => $this->tenantProfiles->activeMobileMoneyProfileForCompany($companyId) !== null,
            ],
            // Le paiement sur place (cash / carte au terminal) reste toujours
            // possible : c'est le fallback affiché au client public.
            'pay_on_site' => true,
        ];
    }
}
