<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporting opérationnel FuelStation (FUEL-017, issue #5811).
 *
 * Construit des read models PRÉ-AGRÉGÉS par (station, type, période) :
 * dashboards sans jointures profondes, recalcul idempotent (upsert sur la
 * clé unique — rejouer remplace, jamais de doublon), temps de génération
 * borné (p95 documenté : périmètre borné par station/période).
 */
final class FuelReportingService
{
    /**
     * Lit le snapshot (généré) ou le calcule à la volée.
     *
     * @return array{snapshot: FuelReportSnapshot, recomputed: bool}
     */
    public function snapshot(FuelStation $station, string $type, string $periodStart, string $periodEnd, ?Employee $actor = null): array
    {
        $existing = FuelReportSnapshot::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('snapshot_type', $type)
            ->where('period_start', Carbon::parse($periodStart)->toDateString())
            ->where('period_end', Carbon::parse($periodEnd)->toDateString())
            ->first();

        if ($existing instanceof FuelReportSnapshot) {
            return ['snapshot' => $existing, 'recomputed' => false];
        }

        $snapshot = $this->compute($station, $type, $periodStart, $periodEnd, $actor);

        return ['snapshot' => $snapshot, 'recomputed' => true];
    }

    private function compute(FuelStation $station, string $type, string $periodStart, string $periodEnd, ?Employee $actor): FuelReportSnapshot
    {
        $payload = match ($type) {
            FuelReportSnapshot::TYPE_PUMP_VOLUMES => $this->pumpVolumes($station, $periodStart, $periodEnd),
            FuelReportSnapshot::TYPE_SALES => $this->sales($station, $periodStart, $periodEnd),
            FuelReportSnapshot::TYPE_SHIFTS => $this->shifts($station, $periodStart, $periodEnd),
            FuelReportSnapshot::TYPE_VARIANCES => $this->variances($station, $periodStart, $periodEnd),
            FuelReportSnapshot::TYPE_STOCK => $this->stock($station),
            FuelReportSnapshot::TYPE_STATION_PERFORMANCE => $this->performance($station, $periodStart, $periodEnd),
            default => abort(404, 'SNAPSHOT_TYPE_UNKNOWN'),
        };

        return FuelReportSnapshot::query()->updateOrCreate(
            [
                'company_id' => $station->company_id,
                'station_id' => $station->id,
                'snapshot_type' => $type,
                'period_start' => Carbon::parse($periodStart)->toDateString(),
                'period_end' => Carbon::parse($periodEnd)->toDateString(),
            ],
            [
                'payload' => $payload,
                'generated_by' => $actor?->id,
                'generated_at' => Carbon::now('UTC'),
            ]
        );
    }

    /** @return array<string, mixed> */
    private function pumpVolumes(FuelStation $station, string $start, string $end): array
    {
        $rows = DB::table('fuel_meter_readings as r')
            ->join('fuel_pumps as p', function (JoinClause $join): void {
                $join->on('p.id', '=', 'r.pump_id')
                    ->on('p.company_id', '=', 'r.company_id');
            })
            ->where('r.company_id', $station->company_id)
            ->where('r.station_id', $station->id)
            ->whereBetween('r.captured_at_utc', [$start.' 00:00:00', $end.' 23:59:59'])
            ->select('r.pump_id', 'p.code as pump_code', DB::raw('MAX(r.reading_value_minor) - MIN(r.reading_value_minor) as volume_minor'))
            ->groupBy('r.pump_id', 'p.code')
            ->orderBy('p.code')
            ->get();

        return [
            'period_start' => $start,
            'period_end' => $end,
            'pumps' => $rows->map(function (object $row): array {
                $volume = $row->volume_minor ?? 0;

                return [
                    'pump_id' => is_numeric($row->pump_id) ? (int) $row->pump_id : 0,
                    'pump_code' => is_string($row->pump_code) ? $row->pump_code : '',
                    'volume_minor' => is_numeric($volume) ? (int) $volume : 0,
                ];
            })->all(),
        ];
    }

    /**
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     sales: array<int, array{product: string, sale_count: int, quantity_total: float, amount_total: float}>,
     *     totals: array{sale_count: int, quantity_total: float, amount_total: float},
     * }
     */
    private function sales(FuelStation $station, string $start, string $end): array
    {
        $rows = DB::table('fuel_sales')
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->whereBetween('sale_time', [$start.' 00:00:00', $end.' 23:59:59'])
            ->selectRaw('product, COUNT(*) as sale_count, SUM(quantity) as quantity_total, SUM(amount) as amount_total')
            ->groupBy('product')
            ->orderBy('product')
            ->get();

        $saleCount = is_numeric($rows->sum('sale_count')) ? (int) $rows->sum('sale_count') : 0;
        $quantityTotal = is_numeric($rows->sum('quantity_total')) ? (float) $rows->sum('quantity_total') : 0.0;
        $amountTotal = is_numeric($rows->sum('amount_total')) ? (float) $rows->sum('amount_total') : 0.0;

        return [
            'period_start' => $start,
            'period_end' => $end,
            'sales' => $rows->map(function (object $row): array {
                return [
                    'product' => is_string($row->product) ? $row->product : '',
                    'sale_count' => is_numeric($row->sale_count) ? (int) $row->sale_count : 0,
                    'quantity_total' => is_numeric($row->quantity_total) ? (float) $row->quantity_total : 0.0,
                    'amount_total' => is_numeric($row->amount_total) ? (float) $row->amount_total : 0.0,
                ];
            })->all(),
            'totals' => [
                'sale_count' => $saleCount,
                'quantity_total' => $quantityTotal,
                'amount_total' => $amountTotal,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function shifts(FuelStation $station, string $start, string $end): array
    {
        $shifts = FuelShift::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->withCount('assignments')
            ->orderBy('start_time')
            ->get();

        return [
            'period_start' => $start,
            'period_end' => $end,
            'shifts' => $shifts->map(fn (FuelShift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'status' => $shift->status,
                'assignments_count' => $shift->assignments_count,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function variances(FuelStation $station, string $start, string $end): array
    {
        $runs = FuelReconciliationRun::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->whereBetween('run_date', [$start, $end])
            ->orderByDesc('run_date')
            ->get();

        return [
            'period_start' => $start,
            'period_end' => $end,
            'reconciliation_runs' => $runs->map(fn (FuelReconciliationRun $run): array => [
                'id' => $run->id,
                'run_date' => $run->run_date->toDateString(),
                'status' => $run->status,
                'total_variance_minor' => is_array($run->summary) && is_numeric($run->summary['total_variance_minor'] ?? null)
                    ? (int) $run->summary['total_variance_minor']
                    : null,
                'explainable' => is_array($run->summary)
                    ? (bool) ($run->summary['explainable'] ?? false)
                    : null,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function stock(FuelStation $station): array
    {
        $tanks = FuelTank::query()
            ->with('station:id,code,name')
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->orderBy('code')
            ->get();

        return [
            'generated_at' => Carbon::now('UTC')->toIso8601String(),
            'tanks' => $tanks->map(fn (FuelTank $tank): array => [
                'id' => $tank->id,
                'code' => $tank->code,
                'product_type' => $tank->product_type,
                'capacity_minor' => $tank->capacity_minor,
                'current_level_minor' => $tank->current_level_minor,
                'fill_ratio' => $tank->capacity_minor > 0
                    ? round((int) $tank->current_level_minor / (int) $tank->capacity_minor, 4)
                    : 0.0,
                'status' => $tank->status,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function performance(FuelStation $station, string $start, string $end): array
    {
        $sales = $this->sales($station, $start, $end);
        $totals = $sales['totals'];
        $revenue = $totals['amount_total'];
        $count = $totals['sale_count'];

        return [
            'period_start' => $start,
            'period_end' => $end,
            'station_id' => $station->id,
            'station_code' => $station->code,
            'total_revenue' => $revenue,
            'total_sales_count' => $count,
            'total_quantity' => $totals['quantity_total'],
            'average_basket' => $count > 0 ? round($revenue / $count, 2) : 0.0,
        ];
    }
}
