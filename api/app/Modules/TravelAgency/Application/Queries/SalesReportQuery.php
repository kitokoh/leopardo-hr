<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Queries;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * TRAVEL-501 (#6071) — Rapport des ventes (spec §7.6).
 *
 * Filtres : période (created_at), trajet, route, source, statut.
 * - `paginated()` : liste paginée des ventes.
 * - `summary()`   : agrégats serveur (count, passagers, montants minor units).
 * Agrégation côté serveur uniquement ; isolation tenant portée par le
 * middleware `tenant` + BelongsToCompany.
 */
final class SalesReportQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginated(array $filters): LengthAwarePaginator
    {
        $query = TravelBooking::query()
            ->with(['trip.route', 'trip'])
            ->where('status', '!=', 'pending');

        $this->applyFilters($query, $filters);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderByDesc('created_at')->paginate($this->perPage($filters));

        $paginator->appends($filters);

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{booking_count: int, passenger_count: int, total_amount_minor: int}
     */
    public function summary(array $filters): array
    {
        $query = TravelBooking::query()->where('status', '!=', 'pending');
        $this->applyFilters($query, $filters);

        $row = (clone $query)
            ->selectRaw('COUNT(*) as booking_count')
            ->selectRaw('COALESCE(SUM(passenger_count), 0) as passenger_count')
            ->selectRaw('COALESCE(SUM(total_amount_minor), 0) as total_amount_minor')
            ->first();

        return [
            'booking_count' => (int) ($row->booking_count ?? 0),
            'passenger_count' => (int) ($row->passenger_count ?? 0),
            'total_amount_minor' => (int) ($row->total_amount_minor ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['trip_id'])) {
            $query->where('trip_id', $filters['trip_id']);
        }
        if (! empty($filters['route_id'])) {
            $query->whereHas('trip', fn (Builder $t) => $t->where('route_id', $filters['route_id']));
        }
        if (! empty($filters['source'])) {
            $query->where('booking_source', $filters['source']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min(500, (int) ($filters['per_page'] ?? 50)));
    }
}
