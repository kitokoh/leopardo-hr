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
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;use App\Modules\FuelStation\Domain\Models\FuelReportExport;use App\Modules\FuelStation\Domain\Models\FuelSale;use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;use App\Modules\FuelStation\Domain\Models\FuelStockMovement;use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;use Illuminate\Support\Facades\Storage;

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

    public function generateExportFile(FuelReportExport $export): string
    {
        $rows = $this->exportRows($export);

        $disk = Storage::disk('local');
        $path = "fuel_reports/{$export->company_id}/{$export->id}.csv";

        $content = '';
        foreach ($rows as $row) {
            $content .= $this->csvLine($row);
        }

        $disk->put($path, $content);

        return $path;
    }

    private function buildPayload(FuelStation $station, string $reportType, Carbon $date): array
    {
        return match ($reportType) {
            FuelReportSnapshot::TYPE_DAILY_VOLUMES => [
                'station_id' => $station->id,
                'date' => $date->toDateString(),
                'volumes_by_pump' => $this->volumesByPump($station, $date),
            ],
            FuelReportSnapshot::TYPE_SALES_SUMMARY => [
                'station_id' => $station->id,
                'date' => $date->toDateString(),
                'by_product' => $this->salesByProduct($station, $date),
                'totals' => $this->salesTotals($station, $date),
            ],
            FuelReportSnapshot::TYPE_STOCK_STATUS => [
                'station_id' => $station->id,
                'as_of' => now()->toISOString(),
                'tanks' => $this->tankLevels($station),
                'movements_today' => $this->movementsToday($station),
            ],
            FuelReportSnapshot::TYPE_VARIANCE_SUMMARY => [
                'station_id' => $station->id,
                'as_of' => now()->toISOString(),
                'reconciliations' => $this->latestReconciliations($station),
            ],
            FuelReportSnapshot::TYPE_SHIFT_SUMMARY => [
                'station_id' => $station->id,
                'date' => $date->toDateString(),
                'by_shift' => $this->salesByShift($station, $date),
            ],
            default => [],
        };
    }

    private function volumesByPump(FuelStation $station, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $meters = FuelMeterRegister::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->get();

        // N+1 guard (FUEL-020, #5814) : UNE requête agrégée pour tous les
        // deltas de la station, pas une requête par compteur.
        $deltas = FuelMeterInterval::query()
            ->where('company_id', $station->company_id)
            ->whereIn('meter_id', $meters->pluck('id'))
            ->whereIn('calculation_status', [
                FuelMeterInterval::STATUS_VALID,
                FuelMeterInterval::STATUS_ROLLOVER,
            ])
            ->whereBetween('calculated_at', [$start, $end])
            ->selectRaw('meter_id, COALESCE(SUM(delta_minor), 0) as total')
            ->groupBy('meter_id')
            ->pluck('total', 'meter_id');

        $result = [];

        foreach ($meters as $meter) {
            $pump = $meter->getAttribute('pump_id');
            $code = $meter->getAttribute('meter_code');

            $result[] = [
                'meter_code' => is_string($code) ? $code : (string) $meter->id,
                'pump_id' => is_int($pump) ? $pump : null,
                'product_code' => $meter->getAttribute('product_code'),
                'delta_minor' => (int) ($deltas->get($meter->id) ?? 0),
            ];
        }

        return $result;
    }

    private function salesByProduct(FuelStation $station, Carbon $date): array
    {
        $rows = FuelSale::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->whereBetween('sale_time', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->selectRaw('product, COUNT(*) as count, SUM(quantity) as quantity, SUM(amount) as amount')
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

    private function salesTotals(FuelStation $station, Carbon $date): array
    {
        $row = FuelSale::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->whereBetween('sale_time', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(quantity), 0) as quantity, COALESCE(SUM(amount), 0) as amount')
            ->first();

        return [
            'count' => (int) ($row?->getAttribute('count') ?? 0),
            'quantity' => (float) ($row?->getAttribute('quantity') ?? 0),
            'amount' => (float) ($row?->getAttribute('amount') ?? 0),
        ];
    }

    private function tankLevels(FuelStation $station): array
    {
        $tanks = FuelTank::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->orderBy('code')
            ->get();

        return $tanks->map(function (FuelTank $tank): array {
            $capacity = (int) $tank->getAttribute('capacity_minor');
            $level = $tank->getAttribute('current_level_minor');
            $levelInt = is_int($level) ? $level : null;

            return [
                'code' => $tank->getAttribute('code'),
                'product_type' => $tank->getAttribute('product_type'),
                'capacity_minor' => $capacity,
                'current_level_minor' => $levelInt,
                'fill_ratio' => $levelInt !== null && $capacity > 0 ? round($levelInt / $capacity, 4) : null,
            ];
        })->all();
    }

    private function movementsToday(FuelStation $station): array
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        return [
            'in_minor' => (int) FuelStockMovement::query()
                ->where('company_id', $station->company_id)
                ->where('station_id', $station->id)
                ->where('direction', FuelStockMovement::DIRECTION_IN)
                ->whereBetween('movement_at', [$start, $end])
                ->sum('quantity_minor'),
            'out_minor' => (int) FuelStockMovement::query()
                ->where('company_id', $station->company_id)
                ->where('station_id', $station->id)
                ->where('direction', FuelStockMovement::DIRECTION_OUT)
                ->whereBetween('movement_at', [$start, $end])
                ->sum('quantity_minor'),
        ];
    }

    private function latestReconciliations(FuelStation $station): array
    {
        return FuelStockReconciliation::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->orderByDesc('period_end')
            ->limit(10)
            ->get()
            ->map(fn (FuelStockReconciliation $r): array => [
                'id' => $r->id,
                'product_type' => $r->product_type,
                'period_start' => $r->period_start?->toDateString() ?? '',
                'period_end' => $r->period_end?->toDateString() ?? '',
                'status' => $r->status,
                'variance_minor' => $r->variance_minor,
                'tolerance_minor' => $r->variance_tolerance_minor,
            ])
            ->all();
    }

    private function salesByShift(FuelStation $station, Carbon $date): array
    {
        $shiftIds = FuelShift::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->pluck('id');

        $assignments = FuelShiftAssignment::query()
            ->where('company_id', $station->company_id)
            ->whereIn('shift_id', $shiftIds)
            ->where('assignment_date', $date->toDateString())
            ->get();

        // N+1 guard (FUEL-020, #5814) : les noms de shifts sont chargés en
        // UNE requête (pas un find() par affectation).
        $shiftNames = FuelShift::query()
            ->whereIn('id', $shiftIds)
            ->pluck('name', 'id');

        $shiftByEmployee = [];

        foreach ($assignments as $assignment) {
            $employeeId = $assignment->getAttribute('employee_id');
            $shiftId = $assignment->getAttribute('shift_id');
            $shiftByEmployee[(int) $employeeId] = (int) $shiftId;
        }

        $sales = FuelSale::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->whereBetween('sale_time', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->get();

        $byShift = [];

        foreach ($sales as $sale) {
            $employeeId = (int) $sale->employee_id;
            $shiftId = $shiftByEmployee[$employeeId] ?? null;

            $key = $shiftId ?? 0;
            if (! isset($byShift[$key])) {
                $byShift[$key] = [
                    'shift_id' => $shiftId,
                    'shift_name' => $shiftId !== null ? ((string) ($shiftNames[$shiftId] ?? "Shift #{$shiftId}")) : 'sans_shift',
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }

            $byShift[$key]['count']++;
            $byShift[$key]['amount'] += (float) $sale->amount;
        }

        return array_values($byShift);
    }

    private function exportRows(FuelReportExport $export): array
    {
        $date = $export->report_date?->toDateString() ?? now()->toDateString();

        if ($export->report_type === 'referential') {
            return $this->referentialRows($export);
        }

        $rows = [['report_type', 'station_id', 'date', 'label', 'value']];

        $stationId = $export->station_id;

        if ($stationId === null) {
            return $rows;
        }

        /** @var FuelStation|null $station */
        $station = FuelStation::query()->find($stationId);

        if (! $station instanceof FuelStation) {
            return $rows;
        }

        $payload = $this->buildPayload($station, $export->report_type, Carbon::parse($date));

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $rows[] = [$export->report_type, (string) $stationId, $date, $key, is_string($encoded) ? $encoded : ''];
            } else {
                $rows[] = [$export->report_type, (string) $stationId, $date, $key, (string) $value];
            }
        }

        return $rows;
    }

    private function referentialRows(FuelReportExport $export): array
    {
        $companyId = $export->company_id;
        $rows = [['entity', 'station_id', 'code', 'detail']];

        foreach (FuelProduct::query()->where('company_id', $companyId)->orderBy('code')->get() as $product) {
            $rows[] = [
                'product',
                '',
                (string) $product->code,
                sprintf('%s|%s|%s', (string) $product->name, (string) $product->unit_code, (string) $product->status),
            ];
        }

        foreach (FuelPump::query()->where('company_id', $companyId)->orderBy('code')->get() as $pump) {
            $rows[] = [
                'pump',
                (string) $pump->station_id,
                (string) $pump->code,
                implode('|', (array) $pump->product_types).'|'.(string) $pump->status,
            ];
        }

        foreach (FuelTank::query()->where('company_id', $companyId)->orderBy('code')->get() as $tank) {
            $rows[] = [
                'tank',
                (string) $tank->station_id,
                (string) $tank->code,
                sprintf('%s|%d|%s', (string) $tank->product_type, (int) $tank->capacity_minor, (string) $tank->status),
            ];
        }

        foreach (FuelMeterRegister::query()->where('company_id', $companyId)->orderBy('meter_code')->get() as $meter) {
            $rows[] = [
                'meter',
                (string) $meter->station_id,
                (string) $meter->meter_code,
                sprintf('%s|%s|%d|%s', (string) $meter->product_code, (string) $meter->unit_code, (int) $meter->precision_scale, (string) $meter->status),
            ];
        }

        return $rows;
    }

    private function csvLine(array $fields): string
    {
        $escaped = array_map(static fn (string $field): string => sprintf('"%s"', str_replace('"', '""', $field)), $fields);

        return implode(',', $escaped)."\n";
    }
}