<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelReportExport;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Reporting opérationnel FuelStation — FUEL-017 (#5811).
 *
 * Read models sans jointures profondes à la lecture : chaque snapshot est
 * calculé une fois puis servi. Recalcul IDEMPOTENT (upsert par
 * company/station/type/date) : rejouer la même journée réécrit le même
 * payload, jamais de doublon.
 *
 * Types :
 * - daily_volumes : volumes par pompe (deltas de compteurs validés) ;
 * - sales_summary : ventes par produit (nb, quantité, montant) ;
 * - stock_status : niveaux courants par cuve/produit + mouvement du jour ;
 * - variance_summary : derniers rapprochements (statut, écart) ;
 * - shift_summary : ventes par shift via les affectations du jour.
 */
final class FuelReportingService
{
    /**
     * Calcule (ou rejoue) un snapshot. Retourne le snapshot à jour —
     * idempotent par (company, station, type, date).
     *
     * @return array{snapshot: FuelReportSnapshot, recomputed: bool}
     */
    public function compute(
        FuelStation $station,
        string $reportType,
        string $date,
    ): array {
        if (! in_array($reportType, FuelReportSnapshot::TYPES, true)) {
            throw new \InvalidArgumentException('Type de rapport inconnu.');
        }

        $payload = $this->buildPayload($station, $reportType, Carbon::parse($date));

        /** @var FuelReportSnapshot|null $existing */
        $existing = FuelReportSnapshot::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('report_type', $reportType)
            ->where('snapshot_date', $date)
            ->first();

        if ($existing instanceof FuelReportSnapshot) {
            $existing->forceFill([
                'payload' => $payload,
                'computed_at' => now(),
            ])->save();

            return ['snapshot' => $existing->refresh(), 'recomputed' => true];
        }

        /** @var FuelReportSnapshot $snapshot */
        $snapshot = FuelReportSnapshot::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $station->id,
            'report_type' => $reportType,
            'snapshot_date' => $date,
            'payload' => $payload,
            'computed_at' => now(),
        ]);

        return ['snapshot' => $snapshot, 'recomputed' => false];
    }

    /**
     * Génère le fichier CSV d'un export et le pose sur le disque.
     * Retourne le chemin relatif du fichier.
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return list<array{code: string, product_types: list<string>, delta_minor: int}>
     */
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

    /**
     * @return list<array{product: string, count: int, quantity: float, amount: float}>
     */
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

        return $rows->map(fn (FuelSale $row): array => [
            'product' => (string) $row->getAttribute('product'),
            'count' => (int) $row->getAttribute('count'),
            'quantity' => (float) $row->getAttribute('quantity'),
            'amount' => (float) $row->getAttribute('amount'),
        ])->all();
    }

    /** @return array{count: int, quantity: float, amount: float} */
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

    /**
     * @return list<array{code: string, product_type: string, capacity_minor: int, current_level_minor: int|null, fill_ratio: float|null}>
     */
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

    /** @return array{in_minor: int, out_minor: int} */
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

    /**
     * @return list<array{id: int, product_type: string, period_start: string, period_end: string, status: string, variance_minor: int, tolerance_minor: int}>
     */
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

    /**
     * Ventes du jour par shift : les ventes sont rattachées à un employé ;
     * l'affectation du jour relie l'employé à son shift.
     *
     * @return list<array{shift_id: int|null, shift_name: string, count: int, amount: float}>
     */
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

    /**
     * Lignes CSV d'un export (rapports de la journée ou référentiel).
     *
     * @return list<list<string>>
     */
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

    /**
     * Export contrôlé du référentiel (FUEL-018, #5812) : produits et
     * équipements (pompes/cuves/compteurs) du tenant.
     *
     * @return list<list<string>>
     */
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

    /**
     * @param  list<string>  $fields
     */
    private function csvLine(array $fields): string
    {
        $escaped = array_map(static fn (string $field): string => sprintf('"%s"', str_replace('"', '""', $field)), $fields);

        return implode(',', $escaped)."\n";
    }
}
