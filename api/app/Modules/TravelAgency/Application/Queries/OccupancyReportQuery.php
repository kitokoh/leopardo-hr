<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Queries;

use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * TRAVEL-502 (#6072) — Taux d'occupation par trajet (spec §7.6).
 *
 * Taux = sièges vendus / total, borné [0,1], tri par taux décroissant.
 * Calcul serveur exact à partir des sièges — jamais de données d'un
 * autre tenant (BelongsToCompany).
 */
final class OccupancyReportQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $perPage = $this->perPage($filters);
        $page = Paginator::resolveCurrentPage();

        $base = TravelTrip::query()
            ->where('status', 'published');

        if (! empty($filters['from'])) {
            $base->where('departure_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $base->where('departure_date', '<=', $filters['to']);
        }
        if (! empty($filters['route_id'])) {
            $base->where('route_id', $filters['route_id']);
        }

        $total = (clone $base)->count();

        $trips = (clone $base)
            ->withCount(['seats as sold' => fn (Builder $s) => $s->where('status', SeatStatus::SOLD)])
            ->withCount(['seats as reserved' => fn (Builder $s) => $s->where('status', SeatStatus::RESERVED)])
            ->orderByDesc('departure_date')
            ->forPage($page, $perPage)
            ->get();

        $items = $trips->map(function (TravelTrip $trip): array {
            $total = max(1, (int) $trip->total_seats);
            $sold = (int) $trip->getAttribute('sold');
            $reserved = (int) $trip->getAttribute('reserved');
            $rate = min(1.0, max(0.0, $sold / $total));

            return [
                'id' => $trip->id,
                'code' => $trip->code,
                'route_id' => $trip->route_id,
                'departure_date' => $trip->departure_date->toDateString(),
                'departure_time' => $trip->departure_time,
                'total_seats' => $total,
                'sold_seats' => $sold,
                'reserved_seats' => $reserved,
                'free_seats' => $total - $sold - $reserved,
                'occupancy_rate' => round($rate, 4),
            ];
        })->sortByDesc('occupancy_rate')->values();

        return new Paginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $filters,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min(500, (int) ($filters['per_page'] ?? 50)));
    }
}
