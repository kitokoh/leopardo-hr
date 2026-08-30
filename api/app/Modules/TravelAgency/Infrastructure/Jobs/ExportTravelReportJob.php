<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Jobs;

use App\Modules\TravelAgency\Application\Queries\CancellationsReportQuery;
use App\Modules\TravelAgency\Application\Queries\OccupancyReportQuery;
use App\Modules\TravelAgency\Application\Queries\RevenueReportQuery;
use App\Modules\TravelAgency\Application\Queries\SalesReportQuery;
use App\Modules\TravelAgency\Domain\Models\TravelReportExport;
use App\Modules\TravelAgency\Infrastructure\Services\TravelReportExportStorage;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-505 (#6075) — Export CSV idempotent d'un rapport (spec §7.6).
 *
 * Tenant-scoped : la company_id fait partie du hash de requête — deux
 * tenants ne peuvent jamais partager un fichier. Même requête → même
 * hash → même fichier (rejouable, aucun travail dupliqué). Colonnes
 * allowlistées par type de rapport. Historique borné à 50 lignes/tenant.
 *
 * Job synchrone (exécution bornée : ≤ 500 lignes par export) — dispatché
 * via `Bus::dispatchSync` pour un retour immédiat de l'URL signée.
 */
class ExportTravelReportJob
{
    use Dispatchable;

    private const HISTORY_LIMIT = 50;

    public const TYPES = ['sales', 'occupancy', 'revenue', 'cancellations'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly string $companyId,
        private readonly string $reportType,
        private readonly array $filters,
        private readonly ?int $generatedByUserId = null,
    ) {}

    public function handle(
        SalesReportQuery $sales,
        OccupancyReportQuery $occupancy,
        RevenueReportQuery $revenue,
        CancellationsReportQuery $cancellations,
        TravelReportExportStorage $storage,
    ): TravelReportExport {
        if (! in_array($this->reportType, self::TYPES, true)) {
            throw new \InvalidArgumentException("Type de rapport inconnu : {$this->reportType}");
        }

        $hash = $this->requestHash();

        // Idempotence : même requête → même fichier, retour direct.
        $existing = TravelReportExport::query()
            ->where('company_id', $this->companyId)
            ->where('request_hash', $hash)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $rows = $this->rowsForType($sales, $occupancy, $revenue, $cancellations);
        $csv = $this->toCsv($rows);

        $export = DB::transaction(function () use ($hash, $csv, $rows, $storage): TravelReportExport {
            $export = TravelReportExport::query()->create([
                'company_id' => $this->companyId,
                'report_type' => $this->reportType,
                'request_hash' => $hash,
                'filters' => $this->filters,
                'storage_path' => '',
                'mime_type' => 'text/csv; charset=UTF-8',
                'row_count' => max(0, count($rows) - 1),
                'generated_by_user_id' => $this->generatedByUserId,
                'expires_at' => now()->addMinutes(30),
            ]);

            $export->forceFill(['storage_path' => $storage->store($export, $csv)])->save();

            $this->pruneHistory($storage);

            return $export;
        });

        return $export;
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

    /**
     * @return list<list<mixed>>
     */
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

    /**
     * @return list<list<mixed>>
     */
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

    /**
     * @return list<list<mixed>>
     */
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

    /**
     * @return list<list<mixed>>
     */
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

    /**
     * @return list<list<mixed>>
     */
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

    /**
     * @param  list<list<mixed>>  $rows
     */
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

    /**
     * @return array<string, mixed>
     */
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
