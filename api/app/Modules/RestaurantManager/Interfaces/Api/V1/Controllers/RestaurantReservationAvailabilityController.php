<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * RESTO-602 (#6207) — Disponibilité de créneaux (tables, couverts, dates).
 *
 * `GET /restaurant/reservations/availability?branch_id=&reserved_at=&covers=`
 * retourne les tables actives de la branche dont la capacité suffit pour les
 * couverts ET libres sur le créneau demandé (fenêtre de conflit ±2h, même
 * règle que la création — spec §4.3). 404 si la branche est hors tenant.
 */
class RestaurantReservationAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantReservation::class)) {
            abort(403);
        }

        $branchId = $request->query('branch_id');
        $reservedAt = $request->query('reserved_at');
        $covers = (int) ($request->query('covers', 1));

        if (! is_numeric($branchId) || ! is_string($reservedAt)) {
            abort(422, 'branch_id and reserved_at are required.');
        }

        if ($covers < 1 || $covers > 999) {
            abort(422, 'covers must be between 1 and 999.');
        }

        $branch = RestaurantBranch::query()
            ->where('company_id', $actor->company_id)
            ->find((int) $branchId);

        if (! $branch instanceof RestaurantBranch) {
            abort(404);
        }

        $slot = Carbon::parse($reservedAt);

        $tables = RestaurantTable::query()
            ->where('company_id', $actor->company_id)
            ->where('branch_id', $branch->id)
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->where('capacity', '>=', $covers)
            ->orderBy('label')
            ->get();

        $available = $tables->filter(function (RestaurantTable $table) use ($actor, $branch, $slot): bool {
            $conflict = RestaurantReservation::query()
                ->where('company_id', $actor->company_id)
                ->where('branch_id', $branch->id)
                ->where('table_id', $table->id)
                ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value, ReservationStatus::SEATED->value])
                ->whereBetween('reserved_at', [$slot->copy()->subHours(2), $slot->copy()->addHours(2)])
                ->exists();

            return ! $conflict;
        });

        return new JsonResponse([
            'data' => [
                'branch_id' => $branch->id,
                'reserved_at' => $slot->toIso8601String(),
                'covers' => $covers,
                'available_tables' => $available->map(fn (RestaurantTable $table) => [
                    'id' => $table->id,
                    'label' => $table->label,
                    'capacity' => $table->capacity,
                    'zone_id' => $table->zone_id,
                ])->values(),
            ],
        ]);
    }
}
