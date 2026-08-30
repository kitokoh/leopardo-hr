<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Queries;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * TRAVEL-503 (#6073) — Recettes encaissées (spec §7.6).
 *
 * Recettes = paiements `confirmed` − paiements `refunded`, par période /
 * route / source. Égalité vérifiée avec les montants des paiements
 * confirmés/remboursés (test de parité dans TravelReportApiTest).
 */
final class RevenueReportQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{confirmed_minor: int, refunded_minor: int, net_minor: int, by_route: array<int, array{route_id: int, confirmed_minor: int, refunded_minor: int, net_minor: int}>}
     */
    public function execute(array $filters): array
    {
        $confirmed = $this->sumByStatus($filters, PaymentStatus::CONFIRMED);
        $refunded = $this->sumByStatus($filters, PaymentStatus::REFUNDED);

        $byRoute = $this->groupByRoute($filters);

        return [
            'confirmed_minor' => $confirmed,
            'refunded_minor' => $refunded,
            'net_minor' => $confirmed - $refunded,
            'by_route' => $byRoute,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function sumByStatus(array $filters, PaymentStatus $status): int
    {
        $query = TravelPayment::query()
            ->where('status', $status->value)
            ->selectRaw('COALESCE(SUM(amount_minor), 0) as total');

        $this->applyPeriod($query, $filters);

        $row = $query->first();

        return (int) ($row->total ?? 0);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{route_id: int, confirmed_minor: int, refunded_minor: int, net_minor: int}>
     */
    private function groupByRoute(array $filters): array
    {
        $query = TravelPayment::query()
            ->join('travel_bookings', 'travel_bookings.id', '=', 'travel_payments.booking_id')
            ->join('travel_trips', 'travel_trips.id', '=', 'travel_bookings.trip_id')
            ->select('travel_trips.route_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN travel_payments.status = ? THEN travel_payments.amount_minor ELSE 0 END), 0) as confirmed_minor', [PaymentStatus::CONFIRMED->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN travel_payments.status = ? THEN travel_payments.amount_minor ELSE 0 END), 0) as refunded_minor', [PaymentStatus::REFUNDED->value])
            ->groupBy('travel_trips.route_id')
            ->orderByDesc('confirmed_minor');

        $this->applyPeriod($query, $filters);

        /** @var Collection<int, object> $rows */
        $rows = $query->get();

        return $rows->map(fn (object $row): array => [
            'route_id' => (int) $row->route_id,
            'confirmed_minor' => (int) $row->confirmed_minor,
            'refunded_minor' => (int) $row->refunded_minor,
            'net_minor' => (int) $row->confirmed_minor - (int) $row->refunded_minor,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function applyPeriod($query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('travel_payments.created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('travel_payments.created_at', '<=', $filters['to']);
        }
    }
}
