<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;

/**
 * REST-OS-2 (Mission Leopardo Restaurant OS) — Implémentation Eloquent du port
 * de persistance des travaux d'impression (pattern RestaurantOrderRepository :
 * scoping tenant).
 */
final class RestaurantPrintJobRepository implements RestaurantPrintJobRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?RestaurantPrintJob
    {
        return RestaurantPrintJob::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function findByKey(
        string $companyId,
        int $orderId,
        PrintTicketKind $kind,
        int $revision,
    ): ?RestaurantPrintJob {
        return RestaurantPrintJob::query()
            ->where('company_id', $companyId)
            ->where('order_id', $orderId)
            ->where('ticket_kind', $kind->value)
            ->where('revision', $revision)
            ->first();
    }
}
