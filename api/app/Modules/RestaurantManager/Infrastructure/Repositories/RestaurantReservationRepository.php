<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantReservationRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Illuminate\Support\Carbon;

/**
 * RESTO-215, issue #6180 — Implémentation Eloquent du port de persistance
 * des réservations restaurant (pattern CrmLeadRepository : scoping tenant).
 */
final class RestaurantReservationRepository implements RestaurantReservationRepositoryInterface
{
    /**
     * Demi-largeur de la fenêtre de chevauchement autour de reserved_at.
     */
    private const OVERLAP_WINDOW_HOURS = 2;

    public function findForCompany(int $id, string $companyId): ?RestaurantReservation
    {
        return RestaurantReservation::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function existsOverlapping(string $companyId, int $branchId, ?int $tableId, Carbon $reservedAt, int $covers): bool
    {
        $query = RestaurantReservation::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::SEATED->value,
            ])
            ->whereBetween('reserved_at', [
                $reservedAt->copy()->subHours(self::OVERLAP_WINDOW_HOURS),
                $reservedAt->copy()->addHours(self::OVERLAP_WINDOW_HOURS),
            ]);

        // « Même table » : table ciblée si fournie, sinon réservations sans
        // table (table_id null) — les créneaux en conflit sur une table
        // affectée plus tard seront détectés au check-in (conflit 409).
        if ($tableId === null) {
            $query->whereNull('table_id');
        } else {
            $query->where('table_id', $tableId);
        }

        return $query->exists();
    }
}
