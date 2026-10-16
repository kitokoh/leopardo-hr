<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;

/**
 * REST-OS-2 (Mission Leopardo Restaurant OS) — Port de persistance des travaux
 * d'impression thermique (tenant-scoped).
 *
 * Même pattern que RestaurantOrderRepositoryInterface : scoping tenant
 * systématique (hors tenant → null → 404 sûr).
 */
interface RestaurantPrintJobRepositoryInterface
{
    /**
     * Charge un travail scopé au tenant. null si absent OU hors tenant.
     */
    public function findForCompany(int $id, string $companyId): ?RestaurantPrintJob;

    /**
     * Charge un travail par sa clé métier (commande, type, révision) — la même
     * clé que la contrainte unique : sert à l'idempotence applicative.
     */
    public function findByKey(
        string $companyId,
        int $orderId,
        PrintTicketKind $kind,
        int $revision,
    ): ?RestaurantPrintJob;
}
