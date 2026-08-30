<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Queries;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-507 (#6077) — KPIs du dashboard (spec §7.6).
 *
 * Ventes du jour, passagers, recettes, occupation moyenne, annulations —
 * période configurable (`from`/`to`, défaut aujourd'hui). Les KPIs sont
 * calculés sur les mêmes tables que les rapports détaillés (cohérence
 * vérifiée par TravelDashboardTest).
 */
final class DashboardKpisQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{sales_minor: int, bookings_count: int, passengers_count: int, revenue_minor: int, refunds_minor: int, occupancy_rate: float, cancellations_count: int, period: array{from: string, to: string}}
     */
    public function execute(array $filters): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from']) : now()->startOfDay();
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $sales = TravelBooking::query()
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::COMPLETED])
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(total_amount_minor), 0) as sales')
            ->selectRaw('COUNT(*) as bookings')
            ->selectRaw('COALESCE(SUM(passenger_count), 0) as passengers')
            ->first();

        $payments = TravelPayment::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount_minor ELSE 0 END), 0) as revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'refunded' THEN amount_minor ELSE 0 END), 0) as refunds")
            ->first();

        $trips = TravelTrip::query()
            ->where('status', 'published')
            ->whereBetween('departure_date', [$from, $to])
            ->withCount(['seats as sold' => fn ($q) => $q->where('status', SeatStatus::SOLD)])
            ->get();

        $occupancy = $trips->isEmpty()
            ? 0.0
            : round($trips->sum(fn (TravelTrip $t) => min(1.0, (int) $t->getAttribute('sold') / max(1, (int) $t->total_seats))) / $trips->count(), 4);

        $cancellations = TravelBooking::query()
            ->where('status', BookingStatus::CANCELLED)
            ->whereBetween('cancelled_at', [$from, $to])
            ->count();

        return [
            'sales_minor' => (int) ($sales->sales ?? 0),
            'bookings_count' => (int) ($sales->bookings ?? 0),
            'passengers_count' => (int) ($sales->passengers ?? 0),
            'revenue_minor' => (int) ($payments->revenue ?? 0),
            'refunds_minor' => (int) ($payments->refunds ?? 0),
            'occupancy_rate' => $occupancy,
            'cancellations_count' => (int) $cancellations,
            'period' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
        ];
    }
}
