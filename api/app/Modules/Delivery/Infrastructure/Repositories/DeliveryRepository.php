<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Infrastructure\Repositories;

use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Models\Delivery;

/**
 * DELIVERY-2xx — Implémentation Eloquent du port de persistance des
 * livraisons (pattern CrmLeadRepository / RestaurantOrderRepository :
 * scoping tenant systématique).
 */
final class DeliveryRepository implements DeliveryRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?Delivery
    {
        return Delivery::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function findByReference(string $reference, string $companyId): ?Delivery
    {
        return Delivery::query()
            ->where('company_id', $companyId)
            ->where('reference', $reference)
            ->first();
    }

    public function findBySourceReference(string $source, string $sourceReference, string $companyId): ?Delivery
    {
        return Delivery::query()
            ->where('company_id', $companyId)
            ->where('source', $source)
            ->where('source_reference', $sourceReference)
            ->first();
    }
}
