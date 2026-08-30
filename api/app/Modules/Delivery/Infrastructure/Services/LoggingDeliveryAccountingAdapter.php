<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Infrastructure\Services;

use App\Modules\Delivery\Domain\Contracts\DeliveryAccountingContract;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation par défaut du contrat BC-08 (DELIVERY-205, issue #6289).
 *
 * Seam : tant que les écritures comptables source-référencées ne sont pas
 * branchées, le posting est journalisé (channel structured, aucun montant ni
 * PII dans le message — les valeurs partent en champs structurés) et retourne
 * une référence stable `COD-<settlementId>` (idempotente par construction).
 */
final class LoggingDeliveryAccountingAdapter implements DeliveryAccountingContract
{
    public function postCodSettlement(string $companyId, int $settlementId, int $collectedMinor, ?int $commissionMinor): string
    {
        Log::channel('structured')->info('delivery.cod.posting', [
            'company_id' => $companyId,
            'settlement_id' => $settlementId,
            'collected_minor' => $collectedMinor,
            'commission_minor' => $commissionMinor,
            'adapter' => 'logging',
            'status' => 'seam-not-wired',
        ]);

        return 'COD-'.$settlementId;
    }
}
