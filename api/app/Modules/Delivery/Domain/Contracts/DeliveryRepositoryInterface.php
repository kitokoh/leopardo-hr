<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Contracts;

use App\Modules\Delivery\Domain\Models\Delivery;

/**
 * DELIVERY-2xx — Port de persistance des livraisons (tenant-scoped).
 *
 * Toutes les lectures sont scopées `company_id` (404 sûr hors tenant).
 */
interface DeliveryRepositoryInterface
{
    /**
     * Charge une livraison scopée au tenant. null si absente OU hors tenant.
     */
    public function findForCompany(int $id, string $companyId): ?Delivery;

    /**
     * Charge une livraison par référence (DLV-YYYY-NNNNNN), scopée au tenant.
     */
    public function findByReference(string $reference, string $companyId): ?Delivery;

    /**
     * Charge une livraison par (source, source_reference) — zéro doublon par
     * commande source (unique (company_id, source, source_reference)).
     */
    public function findBySourceReference(string $source, string $sourceReference, string $companyId): ?Delivery;
}
