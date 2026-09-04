<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\TravelAgency\Application\Queries\CancellationsReportQuery;
use App\Modules\TravelAgency\Application\Queries\OccupancyReportQuery;
use App\Modules\TravelAgency\Application\Queries\RevenueReportQuery;
use App\Modules\TravelAgency\Application\Queries\SalesReportQuery;
use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use App\Modules\TravelAgency\Domain\Models\TravelReportExport;
use App\Modules\TravelAgency\Infrastructure\Exports\TravelCsvExporter;
use App\Modules\TravelAgency\Infrastructure\Services\TravelReportExportStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * TRAVEL-505 (#6075) — Génération asynchrone d'un export CSV (pattern
 * BankExport : pending → generating → generated/failed, contexte tenant via
 * EnsureTenantContext). Rejeu du job → MÊME fichier (données déterministes).
 */
class ExportTravelReportJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $exportAssetId)
    {
        $this->onQueue('documents');
    }

    public function tenantCompanyId(): ?string
    {
        /** @var TravelExportAsset|null $asset */
        $asset = TravelExportAsset::query()->withoutGlobalScopes()->find($this->exportAssetId);

        return $asset?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(TravelCsvExporter $exporter): void
    {
        /** @var TravelExportAsset $asset */
        $asset = TravelExportAsset::query()->findOrFail($this->exportAssetId);

        if ($asset->status === TravelExportAsset::STATUS_GENERATED) {
            return;
        }

        try {
            $csv = $exporter->generate(
                (string) $asset->company_id,
                (string) $asset->report_type,
                (string) ($asset->from_at?->toIso8601String() ?? now()->subDays(30)->toIso8601String()),
                (string) ($asset->to_at?->toIso8601String() ?? now()->toIso8601String()),
            );

            $path = sprintf('exports/travel/%s/%s-%s.csv', $asset->company_id, $asset->report_type, $asset->idempotency_key);

            Storage::disk('local')->put($path, $csv);

            $asset->forceFill([
                'status' => TravelExportAsset::STATUS_GENERATED,
                'file_path' => $path,
                'expires_at' => now()->addMinutes(TravelExportAsset::SIGNED_URL_TTL_MINUTES),
                'error_redacted' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::error('travel.export.failed', [
                'export_asset_id' => $asset->id,
                'company_id' => $asset->company_id,
                'error' => $e->getMessage(),
            ]);

            $asset->forceFill([
                'status' => TravelExportAsset::STATUS_FAILED,
                'error_redacted' => substr((string) $e->getMessage(), 0, 500),
            ])->save();

            throw $e;
        }
    }

    private function requestHash(): string
    {
        // Hash déterministe (sha256, pas bcrypt salé) — l'idempotence
        // repose sur l'égalité exacte des hashes entre deux requêtes.
        return hash('sha256', json_encode([
            'company_id' => $this->companyId,
            'report_type' => $this->reportType,
            'filters' => $this->normalizedFilters(),
        ], JSON_THROW_ON_ERROR));
    }

    private function rowsForType(
        SalesReportQuery $sales,
        OccupancyReportQuery $occupancy,
        RevenueReportQuery $revenue,
        CancellationsReportQuery $cancellations,
    ): array {
        return match ($this->reportType) {
            'sales' => $this->salesRows($sales),
            'occupancy' => $this->occupancyRows($occupancy),
            'revenue' => $this->revenueRows($revenue),
            'cancellations' => $this->cancellationRows($cancellations),
            default => [],
        };
    }

    private function salesRows(SalesReportQuery $sales): array
    {
        $rows = [[
            'Référence', 'Date', 'Trajet', 'Route', 'Source', 'Statut',
            'Passagers', 'Montant (minor units)', 'Devise',
        ]];

        foreach ($sales->paginated(array_merge($this->filters, ['per_page' => 500]))->items() as $booking) {
            $rows[] = [
                $booking->reference,
                $booking->created_at?->toDateTimeString(),
                $booking->trip?->code,
                $booking->trip?->route_id,
                $booking->booking_source->value,
                $booking->status->value,
                $booking->passenger_count,
                $booking->total_amount_minor,
                $booking->currency,
            ];
        }

        return $rows;
    }

    private function occupancyRows(OccupancyReportQuery $occupancy): array
    {
        $rows = [[
            'Trajet', 'Date départ', 'Heure départ', 'Route',
            'Total sièges', 'Vendus', 'Réservés', 'Libres', 'Taux',
        ]];

        foreach ($occupancy->execute(array_merge($this->filters, ['per_page' => 500]))->items() as $row) {
            $rows[] = [
                $row['code'],
                $row['departure_date'],
                $row['departure_time'],
                $row['route_id'],
                $row['total_seats'],
                $row['sold_seats'],
                $row['reserved_seats'],
                $row['free_seats'],
                $row['occupancy_rate'],
            ];
        }

        return $rows;
    }

    private function revenueRows(RevenueReportQuery $revenue): array
    {
        $data = $revenue->execute($this->filters);

        $rows = [['Indicateur', 'Montant (minor units)']];
        $rows[] = ['Recettes confirmées', $data['confirmed_minor']];
        $rows[] = ['Remboursements', $data['refunded_minor']];
        $rows[] = ['Net', $data['net_minor']];

        $rows[] = [];
        $rows[] = ['Route ID', 'Confirmé', 'Remboursé', 'Net'];
        foreach ($data['by_route'] as $byRoute) {
            $rows[] = [$byRoute['route_id'], $byRoute['confirmed_minor'], $byRoute['refunded_minor'], $byRoute['net_minor']];
        }

        return $rows;
    }

    private function cancellationRows(CancellationsReportQuery $cancellations): array
    {
        $data = $cancellations->execute($this->filters);

        $rows = [['Indicateur', 'Valeur']];
        $rows[] = ['Annulations', $data['cancelled_count']];
        $rows[] = ['Réservations définitives', $data['total_final_count']];
        $rows[] = ['Taux d\'annulation', $data['cancellation_rate']];

        $rows[] = [];
        $rows[] = ['Motif', 'Nombre'];
        foreach ($data['by_reason'] as $byReason) {
            $rows[] = [$byReason['reason'], $byReason['count']];
        }

        return $rows;
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ouvrir un flux temporaire pour le CSV.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($v) => $v === null ? '' : $v, $row), ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function normalizedFilters(): array
    {
        $filters = $this->filters;
        ksort($filters);

        return $filters;
    }

    private function pruneHistory(TravelReportExportStorage $storage): void
    {
        $ids = TravelReportExport::query()
            ->where('company_id', $this->companyId)
            ->orderByDesc('id')
            ->pluck('id');

        $excess = $ids->slice(self::HISTORY_LIMIT);

        if ($excess->isEmpty()) {
            return;
        }

        foreach ($excess as $id) {
            /** @var TravelReportExport|null $old */
            $old = TravelReportExport::query()->find($id);
            if ($old !== null) {
                $storage->delete($old->storage_path);
                $old->delete();
            }
        }
    }
}
