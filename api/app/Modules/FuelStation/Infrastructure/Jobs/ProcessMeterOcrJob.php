<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\FuelStation\Domain\Models\FuelMeterOcrRequest;
use App\Modules\FuelStation\Infrastructure\Services\MeterOcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI-002 (#6771) — traitement OCR d'une photo de compteur FuelStation.
 *
 * Dispatched par MeterOcrService::submit() APRÈS persistance de la ligne
 * `fuel_meter_ocr_requests` (une perte de queue ne perd jamais la demande).
 * Statuts : queued → processing → succeeded | needs_review | rejected |
 * failed.
 *
 * Retry : `$tries=3` avec `$backoff=[10,60]` — seuls les échecs transitoires
 * (fournisseur indisponible/timeout, erreur de transport) RE-THROW depuis le
 * service ; après épuisement, le job part en `failed_jobs` (dead-letter) et
 * la ligne conserve son statut `failed` (rejouable/auditable). `$timeout=120`
 * borne l'inférence.
 *
 * Tenant : implémente `TenantScopedJob` (middleware `EnsureTenantContext`)
 * comme `CrmImportCommitJob` — le middleware établit search_path +
 * current_company avant exécution, indispensable en mode tenancy « schema ».
 * Aucun contexte d'authentification requis : tout est résolu depuis les ids
 * de la ligne dans le tenant de company_id.
 *
 * Unique : une seule exécution par demande (`uniqueId`), lock relâché à la
 * fin du traitement.
 */
class ProcessMeterOcrJob implements ShouldQueue, ShouldBeUnique, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public int $timeout = 120;

    public function __construct(
        public readonly int $requestId,
        public readonly string $companyId,
    ) {}

    public function uniqueId(): string
    {
        return 'meter-ocr:'.$this->requestId;
    }

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(MeterOcrService $service): void
    {
        /** @var FuelMeterOcrRequest|null $requestRow */
        $requestRow = FuelMeterOcrRequest::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->find($this->requestId);

        if (! $requestRow instanceof FuelMeterOcrRequest) {
            Log::channel('structured')->warning('fuel.meter_ocr_job.request_not_found', [
                'request_id' => $this->requestId,
                'company_id' => $this->companyId,
            ]);

            return;
        }

        // Les échecs transitoires remontent (RuntimeException) : la queue
        // réessaie avec backoff — aucune exception avalée ici.
        $service->process($requestRow);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[ProcessMeterOcrJob] echec definitif apres retries', [
            'request_id' => $this->requestId,
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
        ]);
    }
}
