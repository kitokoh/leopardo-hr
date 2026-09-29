<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Génération asynchrone de l'export CSV des livraisons (BC-26-D07, issue
 * #6295) — tenant-scoped (pattern GenerateBankExportJob), retry borné (3),
 * file `documents`, observable (pending → generating → done/failed).
 *
 * #8206 (BOS-017) : `ShouldBeUnique` (clé = export) — un double-clic ou un
 * double dispatch ne produit qu'une seule exécution.
 */
class GenerateDeliveryExportJob implements ShouldBeUnique, ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * #8206 (BOS-017) — verrou d'unicité au niveau queue : un même export ne
     * doit jamais être généré par deux workers en parallèle (pattern
     * GenerateBankExportJob, TTL aligné sur le timeout).
     */
    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return 'delivery-export:'.$this->exportId;
    }

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('documents');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var DeliveryExport|null $export */
        $export = DeliveryExport::query()->withoutGlobalScopes()->find($this->exportId);

        return $this->resolvedCompanyId = $export?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(): void
    {
        /** @var DeliveryExport|null $export */
        $export = DeliveryExport::query()->withoutGlobalScopes()->find($this->exportId);

        if ($export === null) {
            Log::channel('structured')->warning('delivery.export.missing', ['export_id' => $this->exportId]);

            return;
        }

        $export->forceFill(['status' => 'generating', 'error_message' => null])->save();

        try {
            $deliveries = Delivery::query()
                ->where('company_id', $export->company_id)
                ->whereBetween('created_at', [
                    $export->from_date->startOfDay(),
                    $export->to_date->endOfDay(),
                ])
                ->orderByDesc('created_at')
                ->get();

            $handle = fopen('php://temp', 'w+');
            if ($handle === false) {
                throw new \RuntimeException("Impossible d'ouvrir le flux CSV de sortie.");
            }
            fputcsv($handle, [
                'reference', 'source', 'type', 'status', 'cod_amount_minor',
                'dropoff_address', 'created_at', 'delivered_at',
            ]);

            foreach ($deliveries as $delivery) {
                fputcsv($handle, [
                    $delivery->reference,
                    $delivery->source,
                    $delivery->type,
                    $delivery->status,
                    $delivery->cod_amount_minor,
                    $delivery->dropoff_address,
                    $delivery->created_at?->toIso8601String(),
                    $delivery->delivered_at?->toIso8601String(),
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            $filename = sprintf(
                'delivery_exports/%s_%s_%s.csv',
                $export->company_id,
                $export->from_date->format('Y_m_d'),
                $export->to_date->format('Y_m_d'),
            );

            Storage::disk('local')->put($filename, $content);

            $export->forceFill([
                'status' => 'done',
                'filename' => $filename,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'failed',
                'error_message' => substr($exception->getMessage(), 0, 500),
            ])->save();

            Log::channel('structured')->error('delivery.export.failed', [
                'export_id' => $this->exportId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception; // retry borné (tries=3) puis DLQ
        }
    }
}
