<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Contracts;

/**
 * Contrat BC-08 (comptabilité) pour la livraison (DELIVERY-205, issue #6289).
 *
 * Posting **idempotent** des encaissements COD + commissions : une seule
 * écriture par règlement, même rejoué. La résolution du contrat (implémentation
 * réelle branchée sur les écritures comptables) est un follow-up BC-08 ; le
 * défaut (`LoggingDeliveryAccountingAdapter`) génère une référence stable et
 * journalise sans PII.
 */
interface DeliveryAccountingContract
{
    /**
     * Enregistre le posting comptable d'un règlement COD.
     *
     * @return string référence comptable stable (source-référencée)
     */
    public function postCodSettlement(string $companyId, int $settlementId, int $collectedMinor, ?int $commissionMinor): string;
}
