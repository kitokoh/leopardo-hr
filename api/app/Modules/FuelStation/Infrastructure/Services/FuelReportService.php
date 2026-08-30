<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use Illuminate\Support\Carbon;

/**
 * FUEL-017 (#5811) — Reporting opérationnel FuelStation.
 *
 * Agrégats lecture pure (recalcul idempotent : mêmes filtres → mêmes
 * résultats) pour les dashboards : ventes par période/pompe/produit, shifts,
 * sessions de caisse (écarts) et performance station. Pas de jointures
 * profondes — requêtes indexées sur (company_id, station_id, timestamps).
 */
final class FuelReportService
{
    /**
     * @return array{total_amount: float, total_quantity: float, sales_count: int, by_pump: array<int, array{code: string, quantity: float, amount: float}>, by_product: array<int, array{product: string, quantity: float, amount: float}>}
     */
    public function sales(string $companyId, Carbon $from, Carbon $to, ?int $stationId = null): array
    {
        $query = FuelSale::query()
            ->where('company_id', $companyId)
            ->whereBetween('sale_time', [$from, $to]);

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        $sales = $query->get();

        return [
            'total_amount' => round((float) $sales->sum('amount'), 2),
            'total_quantity' => round((float) $sales->sum('quantity'), 3),
            'sales_count' => $sales->count(),
            'by_pump' => $sales->groupBy('pump_id')
                ->map(fn ($rows) => [
                    'code' => (string) ($rows->first()->pump->code ?? $rows->first()->pump_id),
                    'quantity' => round((float) $rows->sum('quantity'), 3),
                    'amount' => round((float) $rows->sum('amount'), 2),
                ])
                ->values()
                ->all(),
            'by_product' => $sales->groupBy('product')
                ->map(fn ($rows) => [
                    'product' => (string) $rows->first()->product,
                    'quantity' => round((float) $rows->sum('quantity'), 3),
                    'amount' => round((float) $rows->sum('amount'), 2),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{shifts_count: int, active_shifts: int, assignments_count: int}
     */
    public function shifts(string $companyId, ?int $stationId = null): array
    {
        $query = FuelShift::query()->where('company_id', $companyId);

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        $shifts = $query->withCount('assignments')->get();

        return [
            'shifts_count' => $shifts->count(),
            'active_shifts' => $shifts->where('status', 'active')->count(),
            'assignments_count' => (int) $shifts->sum('assignments_count'),
        ];
    }

    /**
     * @return array{sessions_count: int, opening_balance: float, closing_balance: float, variance: float, open_sessions: int}
     */
    public function cashSessions(string $companyId, Carbon $from, Carbon $to, ?int $stationId = null): array
    {
        $query = FuelCashSession::query()
            ->where('company_id', $companyId)
            ->whereBetween('opened_at', [$from, $to]);

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        $sessions = $query->get();
        $closed = $sessions->where('status', 'closed');

        return [
            'sessions_count' => $sessions->count(),
            'opening_balance' => round((float) $closed->sum('opening_balance'), 2),
            'closing_balance' => round((float) $closed->sum('closing_balance'), 2),
            'variance' => round((float) $closed->sum('variance'), 2),
            'open_sessions' => $sessions->where('status', 'open')->count(),
        ];
    }

    /**
     * Sérialisation CSV déterministe pour l'export (FUEL-018) — mêmes
     * filtres → mêmes octets.
     */
    public function toCsv(string $companyId, string $type, Carbon $from, Carbon $to, ?int $stationId = null): string
    {
        $rows = match ($type) {
            'sales' => $this->csvSales($companyId, $from, $to, $stationId),
            'shifts' => $this->csvShifts($companyId, $stationId),
            'cash-sessions' => $this->csvCashSessions($companyId, $from, $to, $stationId),
            default => throw new \InvalidArgumentException('Type de rapport inconnu (sales|shifts|cash-sessions).'),
        };

        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvSales(string $companyId, Carbon $from, Carbon $to, ?int $stationId): array
    {
        $rows = [['sale_time', 'station_id', 'pump_id', 'product', 'quantity', 'unit_price', 'amount', 'source']];

        FuelSale::query()
            ->where('company_id', $companyId)
            ->whereBetween('sale_time', [$from, $to])
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->orderBy('sale_time')
            ->get()
            ->each(function (FuelSale $sale) use (&$rows): void {
                $rows[] = [
                    $sale->sale_time?->toDateTimeString() ?? '',
                    (string) $sale->station_id,
                    (string) $sale->pump_id,
                    (string) $sale->product,
                    (string) $sale->quantity,
                    (string) $sale->unit_price,
                    (string) $sale->amount,
                    (string) $sale->source,
                ];
            });

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvShifts(string $companyId, ?int $stationId): array
    {
        $rows = [['id', 'station_id', 'name', 'start_time', 'end_time', 'status']];

        FuelShift::query()
            ->where('company_id', $companyId)
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->orderBy('name')
            ->get()
            ->each(function (FuelShift $shift) use (&$rows): void {
                $rows[] = [
                    (string) $shift->id,
                    (string) $shift->station_id,
                    (string) $shift->name,
                    (string) $shift->start_time,
                    (string) $shift->end_time,
                    (string) $shift->status,
                ];
            });

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvCashSessions(string $companyId, Carbon $from, Carbon $to, ?int $stationId): array
    {
        $rows = [['id', 'station_id', 'opened_at', 'closed_at', 'opening_balance', 'closing_balance', 'expected_balance', 'variance', 'status']];

        FuelCashSession::query()
            ->where('company_id', $companyId)
            ->whereBetween('opened_at', [$from, $to])
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->orderBy('opened_at')
            ->get()
            ->each(function (FuelCashSession $session) use (&$rows): void {
                $rows[] = [
                    (string) $session->id,
                    (string) $session->station_id,
                    $session->opened_at?->toDateTimeString() ?? '',
                    $session->closed_at?->toDateTimeString() ?? '',
                    (string) $session->opening_balance,
                    (string) $session->closing_balance,
                    (string) $session->expected_balance,
                    (string) $session->variance,
                    (string) $session->status,
                ];
            });

        return $rows;
    }
}
