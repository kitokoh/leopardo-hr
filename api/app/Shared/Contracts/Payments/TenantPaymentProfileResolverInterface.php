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

    /**
     * #7728 (BC-25 RESTAURANT) — clés Stripe PROPRES d'une compagnie DONNÉE
     * (profil `stripe_keys` actif). Nécessaire aux surfaces PUBLIQUES
     * (commande en ligne restaurant : le tenant est résolu par le lien signé,
     * pas par le contexte tenant courant). Null si aucun profil actif —
     * l'appelant DOIT refuser le paiement en ligne (fail-closed), jamais de
     * fallback vers les clés plateforme sur ces surfaces.
     *
     * @return array{profile_id: int, secret_key: string, webhook_secret: string, stripe_account_id: string|null}|null
     */
    public function stripeCredentialsForCompany(string $companyId): ?array;

    /**
     * #7728 (BC-25 RESTAURANT) — profil mobile money ACTIF d'une compagnie
     * DONNÉE (opérateur configuré + numéro d'encaissement du restaurateur).
     * Null si aucun profil actif ou opérateur/numéro manquant.
     *
     * @return array{profile_id: int, operator: string, phone_number: string}|null
     */
    public function activeMobileMoneyProfileForCompany(string $companyId): ?array;
}
