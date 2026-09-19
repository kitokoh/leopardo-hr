<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Payments;

/**
 * #7727 (BC-21 BILLING) — contrat PARTAGÉ de résolution des profils de
 * paiement d'un tenant (`tenant_payment_profiles` : clés PSP propres, IBAN,
 * mobile money).
 *
 * Même raison d'être que `PaymentGatewayConfigProviderInterface` : le module
 * Accounting (routage des encaissements des factures clients) ne peut pas
 * importer `Modules/Billing` (garde d'isolation #5584). Le contrat vit dans
 * l'espace partagé ; l'implémentation (`TenantPaymentProfileResolver`) reste
 * dans Billing et est liée par `BillingServiceProvider`.
 */
interface TenantPaymentProfileResolverInterface
{
    /**
     * Clés Stripe PROPRES du tenant COURANT (profil `stripe_keys` actif) —
     * null si le tenant n'en a pas : l'appelant retombe alors sur les clés de
     * la plateforme (comportement historique).
     *
     * @return array{profile_id: int, secret_key: string, webhook_secret: string, stripe_account_id: string|null}|null
     */
    public function activeStripeCredentials(): ?array;

    /**
     * Webhook secret Stripe du profil `stripe_keys` actif d'une compagnie
     * DONNÉE (contexte webhook public, hors tenant courant). Null si aucun.
     *
     * Ce secret sert uniquement de CANDIDAT de vérification HMAC — jamais
     * d'autorisation en soi.
     */
    public function stripeWebhookSecretForCompany(string $companyId): ?string;
}
