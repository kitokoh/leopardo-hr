<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Illuminate\Support\Carbon;

/**
 * RESTO-215, issue #6180 — Port de persistance des réservations restaurant
 * (tenant-scoped).
 */
interface RestaurantReservationRepositoryInterface
{
    /**
     * Charge une réservation scopée au tenant. null si absente OU hors tenant (404 sûr).
     */
    public function findForCompany(int $id, string $companyId): ?RestaurantReservation;

    /**
     * Vérifie l'existence d'une réservation active (pending|confirmed|seated)
     * sur la même table dont le créneau chevauche la fenêtre de 2h autour de
     * reserved_at. Avec tableId null, seules les réservations sans table
     * (table_id null) sont comparées.
     *
     * @param  int  $covers  nombre de couverts de la réservation candidate
     *                       (réservé aux contrôles de capacité table/zone ultérieurs)
     */
    public function existsOverlapping(string $companyId, int $branchId, ?int $tableId, Carbon $reservedAt, int $covers): bool;
}
