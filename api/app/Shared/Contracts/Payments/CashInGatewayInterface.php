<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Payments;

/**
 * #9238 (BC-40 LIBRARY) — contrat PARTAGÉ d'encaissement en ligne.
 *
 * Pourquoi dans `Shared\Contracts` : la bibliothèque doit encaisser une
 * ressource payante via le socle de paiement (BC-38), mais la garde
 * d'isolation des modules (#5584, `check-module-isolation.sh`) interdit un
 * import `Modules/Library -> Modules/Paiements`. Le contrat vit donc dans
 * l'espace partagé ; l'implémentation (qui délègue à `PaymentService`) reste
 * dans Paiements et est liée dans le container par `PaiementsServiceProvider`.
 *
 * Forme de retour NORMALE et minimale : la bibliothèque n'a besoin ni du
 * modèle `PaymentTransaction` ni des rails. Elle n'obtient qu'un identifiant
 * de transaction, un statut normalisé et une référence externe — de quoi
 * attendre la confirmation du webhook sans rien inventer.
 *
 * ⚠️ Un retour `pending`/`processing` n'autorise AUCUN accès : seule une
 * confirmation postérieure (webhook rejouable) le fait. Voir
 * `LibraryPurchaseService::confirm()`.
 */
interface CashInGatewayInterface
{
    /**
     * Initie un encaissement idempotent (même clé = même transaction).
     *
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     transaction_id: int,
     *     status: string,
     *     external_reference: string|null
     * }
     */
    public function cashIn(
        string $companyId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): array;

    /**
     * Statut AUTORITAIRE d'une transaction, lu en base.
     *
     * C'est ce que consulte un consommateur avant d'accorder un droit : le
     * statut d'initiation (`processing`) n'est jamais une autorisation, et le
     * client ne fournit jamais ce statut lui-même (fail-closed).
     *
     * @return array{status: string, amount_minor: int, currency: string}|null
     *         null si la transaction n'existe pas (ou n'appartient pas au tenant).
     */
    public function transactionStatus(int $transactionId, ?string $companyId = null): ?array;
}
