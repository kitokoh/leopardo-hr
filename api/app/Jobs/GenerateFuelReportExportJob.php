<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\FuelStation\Domain\Models\FuelReportExport;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FUEL-017 (#5811) — génération asynchrone d'un export de rapport
 * FuelStation (CSV), hors cycle requête HTTP.
 *
 * Cycle : pending → generating → generated | failed (avec `expires_at`
 * borné à EXPORT_TTL_HOURS). Tenant-scoped via `EnsureTenantContext`.
 */
class GenerateFuelReportExportJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $fuelReportExportId)
    {
        $this->onQueue('documents');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var FuelReportExport|null $export */
        $export = FuelReportExport::query()->withoutGlobalScopes()->find($this->fuelReportExportId);

        return $this->resolvedCompanyId = $export?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(FuelReportingService $reports): void
    {
        /** @var FuelReportExport|null $export */
        $export = FuelReportExport::query()->withoutGlobalScopes()->find($this->fuelReportExportId);

        if ($export === null) {
            Log::warning("GenerateFuelReportExportJob: FuelReportExport #{$this->fuelReportExportId} not found.");

            return;
        }

        $export->forceFill([
            'status' => FuelReportExport::STATUS_GENERATING,
            'error' => null,
        ])->save();

        try {
            $path = $reports->generateExportFile($export);

            $export->forceFill([
                'status' => FuelReportExport::STATUS_GENERATED,
                'file_path' => $path,
                'expires_at' => now()->addHours(FuelReportExport::EXPORT_TTL_HOURS),
            ])->save();
        } catch (Throwable $exception) {
            Log::error("GenerateFuelReportExportJob: échec export #{$this->fuelReportExportId} : {$exception->getMessage()}");

            $export->forceFill([
                'status' => FuelReportExport::STATUS_FAILED,
                'error' => substr($exception->getMessage(), 0, 1000),
            ])->save();
        }
    }
}
