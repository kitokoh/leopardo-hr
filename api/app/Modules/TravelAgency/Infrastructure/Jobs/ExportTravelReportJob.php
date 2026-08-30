<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use App\Modules\TravelAgency\Infrastructure\Exports\TravelCsvExporter;
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
        return [new EnsureTenantContext()];
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
}
