<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Services;

use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read model des rapports livraison (DELIVERY-207, issue #6291).
 *
 * Agrégations simples scopées `company_id` (pas de jointures profondes
 * transactionnelles) — déterministes : même fenêtre → mêmes résultats, quel
 * que soit le nombre de recalculs. Ventilation par source (v0.2 générique
 * multi-tenant : agence vs restaurant vs e-commerce).
 *
 * Fenêtre de dates incluse (borne haute exclusive) ; sans `to`, la borne
 * haute est « maintenant » (déterministe à une requête près).
 */
final class DeliveryReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $companyId, Carbon $from, Carbon $to): array
    {
        $totals = $this->totals($companyId, $from, $to);
        $bySource = $this->bySource($companyId, $from, $to);
        $byDay = $this->byDay($companyId, $from, $to);
        $byDriver = $this->byDriver($companyId, $from, $to);
        $codCollected = $this->codCollected($companyId, $from, $to);

        $deliveries = (int) $totals['deliveries'];
        $delivered = (int) $totals['delivered'];

        return [
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'totals' => [
                'deliveries' => $deliveries,
                'delivered' => $delivered,
                'failed' => (int) $totals['failed'],
                'cancelled' => (int) $totals['cancelled'],
                'returned' => (int) $totals['returned'],
                'success_rate_pct' => $deliveries > 0 ? round(($delivered / $deliveries) * 100, 1) : 0.0,
                'avg_delivery_delay_minutes' => (int) $totals['avg_delay_minutes'],
                'cod_expected_minor' => (int) $totals['cod_expected_minor'],
                'cod_collected_minor' => (int) $codCollected,
            ],
            'by_source' => $bySource,
            'by_day' => $byDay,
            'by_driver' => $byDriver,
        ];
    }

    /**
     * @return array{deliveries: int, delivered: int, failed: int, cancelled: int, returned: int, avg_delay_minutes: int, cod_expected_minor: int}
     */
    private function totals(string $companyId, Carbon $from, Carbon $to): array
    {
        $row = Delivery::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(
                'COUNT(*) AS deliveries,
                 COUNT(*) FILTER (WHERE status = ?) AS delivered,
                 COUNT(*) FILTER (WHERE status = ?) AS failed,
                 COUNT(*) FILTER (WHERE status = ?) AS cancelled,
                 COUNT(*) FILTER (WHERE status = ?) AS returned,
                 COALESCE(AVG(EXTRACT(EPOCH FROM (delivered_at - created_at)) / 60) FILTER (WHERE delivered_at IS NOT NULL), 0) AS avg_delay_minutes,
                 COALESCE(SUM(cod_amount_minor) FILTER (WHERE status = ?), 0) AS cod_expected_minor',
                ['delivered', 'failed', 'cancelled', 'returned', 'delivered'],
            )
            ->first();

        return [
            'deliveries' => (int) ($row->deliveries ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'returned' => (int) ($row->returned ?? 0),
            'avg_delay_minutes' => (int) round((float) ($row->avg_delay_minutes ?? 0)),
            'cod_expected_minor' => (int) ($row->cod_expected_minor ?? 0),
        ];
    }

    /**
     * @return list<array{source: string, deliveries: int, delivered: int, success_rate_pct: float}>
     */
    private function bySource(string $companyId, Carbon $from, Carbon $to): array
    {
        return Delivery::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(
                'source,
                 COUNT(*) AS deliveries,
                 COUNT(*) FILTER (WHERE status = ?) AS delivered',
                ['delivered'],
            )
            ->groupBy('source')
            ->orderBy('deliveries', 'desc')
            ->get()
            ->map(fn ($row): array => [
                'source' => (string) $row->source,
                'deliveries' => (int) $row->deliveries,
                'delivered' => (int) $row->delivered,
                'success_rate_pct' => $row->deliveries > 0 ? round(((int) $row->delivered / (int) $row->deliveries) * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * @return list<array{date: string, deliveries: int, delivered: int}>
     */
    private function byDay(string $companyId, Carbon $from, Carbon $to): array
    {
        return Delivery::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(
                'created_at::date AS day,
                 COUNT(*) AS deliveries,
                 COUNT(*) FILTER (WHERE status = ?) AS delivered',
                ['delivered'],
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->day,
                'deliveries' => (int) $row->deliveries,
                'delivered' => (int) $row->delivered,
            ])
            ->all();
    }

    /**
     * @return list<array{driver_id: int|null, deliveries: int, delivered: int}>
     */
    private function byDriver(string $companyId, Carbon $from, Carbon $to): array
    {
        return Delivery::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->leftJoin('delivery_stops', 'delivery_stops.delivery_id', '=', 'delivery_deliveries.id')
            ->leftJoin('delivery_routes', 'delivery_routes.id', '=', 'delivery_stops.route_id')
            ->whereNull('delivery_stops.deleted_at')
            ->selectRaw(
                'delivery_routes.driver_id AS driver_id,
                 COUNT(DISTINCT delivery_deliveries.id) AS deliveries,
                 COUNT(DISTINCT delivery_deliveries.id) FILTER (WHERE delivery_deliveries.status = ?) AS delivered',
                ['delivered'],
            )
            ->groupBy('delivery_routes.driver_id')
            ->orderByDesc('deliveries')
            ->get()
            ->map(fn ($row): array => [
                'driver_id' => $row->driver_id !== null ? (int) $row->driver_id : null,
                'deliveries' => (int) $row->deliveries,
                'delivered' => (int) $row->delivered,
            ])
            ->all();
    }

    private function codCollected(string $companyId, Carbon $from, Carbon $to): int
    {
        return (int) DeliveryCodSettlement::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('collected_minor');
    }
}
