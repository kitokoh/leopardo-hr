<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Accounting\Domain\Exceptions\PaymentGatewayNotConfiguredException;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;

/**
 * #5272 — Routage des passerelles par pays de l'entreprise (ADR-0017,
 * option A) : DZ → Chargily ; FR/UK/US/CI → Stripe. Tout autre pays refuse
 * le checkout (fail-closed, PAYMENT_GATEWAY_NOT_CONFIGURED).
 *
 * #7727 — le profil `stripe_keys` ACTIF du tenant PRIME sur le routage pays :
 * l'encaissement de la facture client part sur le compte Stripe DU TENANT
 * (métadonnées traçant le profil). Sans profil actif, comportement
 * historique inchangé (clés plateforme, routage pays). La résolution passe
 * par le contrat partagé (garde d'isolation des modules #5584) — jamais par
 * import direct de Modules/Billing.
 */
final class PaymentGatewayFactory
{
    /** @var array<string, PaymentGatewayInterface> pays → passerelle */
    private array $gateways;

    public function __construct(
        ChargilyPaymentGateway $chargily,
        private readonly StripePaymentGateway $stripe,
        private readonly TenantPaymentProfileResolverInterface $tenantProfiles,
    ) {
        $this->gateways = [
            'DZ' => $chargily,
            'FR' => $this->stripe,
            'GB' => $this->stripe,
            'US' => $this->stripe,
            'CI' => $this->stripe,
        ];
    }

    public function forCountry(string $country): PaymentGatewayInterface
    {
        // #7727 — priorité au compte PSP PROPRE du tenant courant.
        $tenantGateway = $this->tenantStripeGateway();
        if ($tenantGateway !== null) {
            return $tenantGateway;
        }

        $gateway = $this->gateways[strtoupper($country)] ?? null;

        if ($gateway === null) {
            throw new PaymentGatewayNotConfiguredException($country);
        }

        return $gateway;
    }

    /**
     * Passerelle Stripe configurée avec les clés PROPRES du tenant courant
     * (profil `stripe_keys` actif) — null sans profil : fallback plateforme.
     */
    private function tenantStripeGateway(): ?StripePaymentGateway
    {
        $credentials = $this->tenantProfiles->activeStripeCredentials();

        if ($credentials === null || $credentials['secret_key'] === '') {
            return null;
        }

        return $this->stripe->withTenantCredentials(
            $credentials['secret_key'],
            $credentials['webhook_secret'],
            $credentials['profile_id'],
        );
    }

    public function byName(string $name): ?PaymentGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->gatewayName() === $name) {
                return $gateway;
            }
        }

        return null;
    }
}
