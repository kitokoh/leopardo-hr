<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporting opérationnel FuelStation — FUEL-017 (issue #5811).
 *
 * Read models calculés à la volée depuis les tables du module (aucune
 * projection séparée nécessaire au stade pilote) :
 * - ventes journalières par station (total, volume, panier moyen, top
 *   produits) ;
 * - synthèse de shift (affectations, ventes, sessions de caisse) ;
 * - anomalies de compteur (intervalles à revoir) sur une période.
 *
 * Tous les agrégats sont tenant-scoped (company_id) et bornés.
 */
final class FuelReportService
{
    /**
     * @return array<string, mixed>
     */
    public function dailySales(string $companyId, ?int $stationId, Carbon $date): array
    {
        $start = $date->startOfDay();
        $end = $date->copy()->endOfDay();

        $query = FuelSale::query()
            ->where('company_id', $companyId)
            ->whereBetween('sale_time', [$start, $end]);

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        $rows = (clone $query)->get();

        $totalAmount = (float) $rows->sum('amount');
        $totalQuantity = (float) $rows->sum('quantity');
        $count = $rows->count();

        $byProduct = $rows->groupBy('product')
            ->map(fn ($group) => [
                'quantity' => round((float) $group->sum('quantity'), 3),
                'amount' => round((float) $group->sum('amount'), 2),
                'sales' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->take(10)
            ->toArray();

        return [
            'station_id' => $stationId,
            'date' => $date->toDateString(),
            'sales_count' => $count,
            'total_amount' => round($totalAmount, 2),
            'total_quantity' => round($totalQuantity, 3),
            'average_basket' => $count > 0 ? round($totalAmount / $count, 2) : 0.0,
            'top_products' => $byProduct,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shiftSummary(string $companyId, int $shiftId, ?Carbon $date = null): array
    {
        $date = $date ?? now();

        $assignments = DB::table('fuel_shift_assignments')
            ->where('company_id', $companyId)
            ->where('shift_id', $shiftId)
            ->where('assignment_date', $date->toDateString())
            ->get();

        $sales = FuelSale::query()
            ->where('company_id', $companyId)
            ->whereBetween('sale_time', [$date->startOfDay(), $date->copy()->endOfDay()])
            ->get();

        $sessions = FuelCashSession::query()
            ->where('company_id', $companyId)
            ->whereDate('opened_at', $date->toDateString())
            ->get();

        return [
            'shift_id' => $shiftId,
            'date' => $date->toDateString(),
            'assignments_count' => $assignments->count(),
            'assignments' => $assignments->map(fn ($a) => [
                'employee_id' => (int) $a->employee_id,
                'status' => (string) $a->status,
            ])->all(),
            'sales_count' => $sales->count(),
            'sales_amount' => round((float) $sales->sum('amount'), 2),
            'cash_sessions_count' => $sessions->count(),
            'cash_sessions_closed' => $sessions->where('status', FuelCashSession::STATUS_CLOSED)->count(),
            'cash_sessions_approved' => $sessions->where('status', FuelCashSession::STATUS_APPROVED)->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function meterAnomalies(string $companyId, ?int $stationId, Carbon $from, Carbon $to): array
    {
        $query = FuelMeterInterval::query()
            ->where('fuel_meter_intervals.company_id', $companyId)
            ->where('fuel_meter_intervals.calculation_status', '!=', FuelMeterInterval::STATUS_VALID)
            ->whereBetween('fuel_meter_intervals.calculated_at', [$from->startOfDay(), $to->copy()->endOfDay()]);

        // fuel_meter_intervals n'a pas de station_id : on joint via le
        // compteur (fuel_meter_registers.station_id) pour filtrer par site.
        if ($stationId !== null) {
            $query->join('fuel_meter_registers', function ($join) use ($companyId): void {
                $join->on('fuel_meter_registers.id', '=', 'fuel_meter_intervals.meter_id')
                    ->where('fuel_meter_registers.company_id', '=', $companyId);
            })->where('fuel_meter_registers.station_id', $stationId);
        }

        return $query->orderByDesc('fuel_meter_intervals.calculated_at')->limit(100)->get()
            ->map(fn (FuelMeterInterval $interval) => [
                'id' => $interval->id,
                'meter_id' => $interval->meter_id,
                'delta_minor' => $interval->delta_minor,
                'calculation_status' => $interval->calculation_status,
                'calculated_at' => $interval->calculated_at->toISOString(),
            ])
            ->values()
            ->all();
    }
}
