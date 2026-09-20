<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Infrastructure\Services;

use App\Modules\Delivery\Domain\Models\Delivery;
use App\Shared\Contracts\Delivery\PublicDeliveryStatus;
use App\Shared\Contracts\Delivery\PublicDeliveryStatusProvider;

/**
 * Adapter Eloquent du port public de suivi de livraison (#7811).
 *
 * Résolution SANS scope tenant (la page de suivi publique marketplace est
 * cross-tenant, pattern PublicDeliveryTrackingController #6288) mais
 * TOUJOURS contrainte au `company_id` fourni — jamais de lecture non bornée.
 * Ne retourne QUE le DTO public fail-closed (statut + horodatages).
 */
final class EloquentPublicDeliveryStatusProvider implements PublicDeliveryStatusProvider
{
    public function findBySourceReference(
        string $companyId,
        string $source,
        string $sourceReference,
    ): ?PublicDeliveryStatus {
        if ($companyId === '' || $sourceReference === '') {
            return null;
        }

        /** @var Delivery|null $delivery */
        $delivery = Delivery::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('source', $source)
            ->where('source_reference', $sourceReference)
            ->first();

        if ($delivery === null) {
            return null;
        }

        return new PublicDeliveryStatus(
            status: $delivery->status,
            createdAt: $delivery->created_at?->toIso8601String(),
            deliveredAt: $delivery->delivered_at?->toIso8601String(),
            failedAt: $delivery->failed_at?->toIso8601String(),
            returnedAt: $delivery->returned_at?->toIso8601String(),
        );
    }
}
