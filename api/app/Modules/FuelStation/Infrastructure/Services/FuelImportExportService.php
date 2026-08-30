<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\HR\Domain\Models\ExportHistory;
use App\Support\CsvCellSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Import/export sécurisé FuelStation — FUEL-018 (issue #5812).
 *
 * - Exports CSV (ventes, relevés) : contenu neutralisé OWASP
 *   (CsvCellSanitizer), audit via `export_history` (+ DataAccessAuditLogger
 *   pour les données sensibles), pagination bornée (10 000 lignes).
 * - Imports CSV (relevés de compteur, entrées de stock) : journalisés dans
 *   `fuel_imports` (statut, compteurs, résumé d'erreurs), traitement
 *   asynchrone idempotent (idempotency_key par ligne) — jamais de secret ni
 *   PII dans les erreurs.
 */
final class FuelImportExportService
{
    /**
     * @param  Collection<int, mixed>  $records
     * @return array{format: string, content: string, filename: string, count: int}
     */
    public function exportCsv(Request $request, Employee $actor, Collection $records, string $type, string $filenamePrefix): array
    {
        $filename = $filenamePrefix.'_'.now()->format('Y-m-d').'.csv';

        if (Schema::hasTable('export_history')) {
            ExportHistory::query()->create([
                'company_id' => (string) $actor->company_id,
                'employee_id' => (string) $actor->id,
                'type' => $type,
                'format' => 'csv',
                'record_count' => $records->count(),
                'filename' => $filename,
                'ip_address' => $request->ip(),
                'user_agent' => (string) mb_substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        }

        return [
            'format' => 'csv',
            'content' => $this->toCsv($records),
            'filename' => $filename,
            'count' => $records->count(),
        ];
    }

    /**
     * Crée le journal d'import (état uploaded) avant traitement asynchrone.
     */
    public function startImport(Employee $actor, string $kind, string $fileName): FuelImport
    {
        return FuelImport::query()->create([
            'company_id' => (string) $actor->company_id,
            'kind' => $kind,
            'file_name' => basename($fileName),
            'status' => FuelImport::STATUS_UPLOADED,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $collection
     */
    private function toCsv(Collection $collection): string
    {
        if ($collection->isEmpty()) {
            return '';
        }

        $headers = array_keys((array) $collection->first());
        $csv = implode(',', $headers)."\n";

        foreach ($collection as $row) {
            $values = array_map(static function (mixed $v): string {
                $str = CsvCellSanitizer::neutralize($v);
                $escaped = str_replace('"', '""', $str);

                return str_contains($escaped, ',') || str_contains($escaped, '"') || str_contains($escaped, "\n")
                    ? '"'.$escaped.'"'
                    : $escaped;
            }, array_values((array) $row));

            $csv .= implode(',', $values)."\n";
        }

        return $csv;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function salesRows(string $companyId, ?string $from, ?string $to): array
    {
        return FuelSale::query()
            ->where('company_id', $companyId)
            ->when($from !== null, fn ($q) => $q->where('sale_time', '>=', $from.' 00:00:00'))
            ->when($to !== null, fn ($q) => $q->where('sale_time', '<=', $to.' 23:59:59'))
            ->orderByDesc('sale_time')
            ->limit(10000)
            ->get()
            ->map(fn (FuelSale $sale) => [
                'id' => $sale->id,
                'station_id' => $sale->station_id,
                'pump_id' => $sale->pump_id,
                'employee_id' => $sale->employee_id,
                'product' => $sale->product,
                'quantity' => $sale->quantity,
                'unit_price' => $sale->unit_price,
                'amount' => $sale->amount,
                'sale_time' => $sale->sale_time->toISOString(),
                'source' => $sale->source,
                'external_id' => $sale->external_id,
            ])
            ->values()
            ->all();
    }
}
