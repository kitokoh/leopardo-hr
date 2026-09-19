<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Payments;

/**
 * #7726 (BC-21 BILLING) — contrat PARTAGÉ de résolution de la configuration
 * des passerelles de paiement de la plateforme (Stripe, Chargily).
 *
 * Pourquoi dans `Shared\Contracts` : le module Accounting (encaissement des
 * factures clients d'un tenant, ADR-0017) consomme la même configuration PSP
 * que le module Billing (abonnements SaaS), mais la garde d'isolation des
 * modules (#5584, `check-module-isolation.sh`) interdit un import
 * `Modules/Accounting -> Modules/Billing`. Le contrat vit donc dans l'espace
 * partagé ; l'implémentation (`GatewaySettingsService`, précédence
 * BDD → fallback env) reste dans Billing et est liée dans le container par
 * `BillingServiceProvider`.
 */
interface PaymentGatewayConfigProviderInterface
{
    public const SOURCE_DATABASE = 'database';

    public const SOURCE_ENV = 'env';

    public const SOURCE_NONE = 'none';

    /**
     * Configuration résolue d'une passerelle, précédence BDD → env.
     *
     * Forme (les clés absentes valent chaîne vide) :
     *  - stripe : secret_key, webhook_secret, price_pilot, price_operations,
     *    price_enterprise, mode, source ;
     *  - chargily : api_key, webhook_secret, mode, source.
     *
     * `source` ∈ {database, env, none} indique d'où viennent les secrets.
     *
     * @return array<string, string>
     */
    public function resolve(string $gateway): array;

    /**
     * Invalide le cache de configuration d'une passerelle (après écriture
     * admin) — le changement de clé est pris en compte sans redéploiement.
     */
    public function flush(string $gateway): void;
}
