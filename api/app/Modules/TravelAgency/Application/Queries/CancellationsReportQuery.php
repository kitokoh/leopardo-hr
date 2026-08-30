<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Queries;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-504 (#6074) — Annulations & motifs (spec §7.6).
 *
 * Agrégats par période / motif / source + taux d'annulation global
 * (annulées / réservations définitives). Le motif est requêtable depuis
 * `travel_bookings.cancel_reason` (persisté par CancelBookingAction).
 */
final class CancellationsReportQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{cancelled_count: int, total_final_count: int, cancellation_rate: float, by_reason: array<int, array{reason: string, count: int}>, by_source: array<int, array{source: string, count: int}>}
     */
    public function execute(array $filters): array
    {
        $cancelledCount = (int) $this->cancelledQuery($filters)->count();
        $totalCount = (int) $this->totalQuery($filters)->count();

        $byReason = $this->groupBy($filters, 'cancel_reason', 'reason');
        $bySource = $this->groupBy($filters, 'booking_source', 'source');

        return [
            'cancelled_count' => $cancelledCount,
            'total_final_count' => $totalCount,
            'cancellation_rate' => $totalCount > 0 ? round($cancelledCount / $totalCount, 4) : 0.0,
            'by_reason' => $byReason,
            'by_source' => $bySource,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{reason: string, count: int}>|array<int, array{source: string, count: int}>
     */
    private function groupBy(array $filters, string $column, string $alias): array
    {
        $rows = DB::table('travel_bookings')
            ->where('status', BookingStatus::CANCELLED->value)
            ->selectRaw("{$column} as {$alias}")
            ->selectRaw('COUNT(*) as count')
            ->groupBy($column)
            ->orderByDesc('count');

        if (! empty($filters['from'])) {
            $rows->where('cancelled_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $rows->where('cancelled_at', '<=', $filters['to']);
        }
        if (! empty($filters['source'])) {
            $rows->where('booking_source', $filters['source']);
        }

        return $rows->get()->map(fn (object $row): array => [
            $alias => (string) $row->{$alias},
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cancelledQuery(array $filters): Builder
    {
        $query = TravelBooking::query()->where('status', BookingStatus::CANCELLED);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function totalQuery(array $filters): Builder
    {
        $query = TravelBooking::query()
            ->whereIn('status', [BookingStatus::CANCELLED, BookingStatus::CONFIRMED, BookingStatus::COMPLETED, BookingStatus::REFUNDED]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('cancelled_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('cancelled_at', '<=', $filters['to']);
        }
        if (! empty($filters['source'])) {
            $query->where('booking_source', $filters['source']);
        }
    }
}
