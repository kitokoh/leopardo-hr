<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Delivery\Application\Services\DeliveryReportService;
use App\Modules\Delivery\Domain\Models\DeliveryDeadLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Export asynchrone des rapports livraison (BC-26-D07, issue #6295).
 *
 * L'endpoint `GET /deliveries/reports/export` reste synchrone (léger,
 * streamé) ; ce job porte l'export volumineux / planifié : snapshot JSON
 * **déterministe** du read model `DeliveryReportService::summary` (2
 * recalculs → mêmes résultats), tenant-scoped avec contexte restauré,
 * retry borné (3 tentatives, backoff 10/30/60 s) et dead-letter sur
 * `delivery_dead_letters` — rejeu via `delivery:replay-dlq` sans doublon
 * (même `runKey` → même fichier, écrasé).
 */
final class ExportDeliveryReportJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $companyId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $runKey,
    ) {
        $this->onQueue('delivery');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            companyId: (string) ($payload['company_id'] ?? ''),
            from: (string) ($payload['from'] ?? ''),
            to: (string) ($payload['to'] ?? ''),
            runKey: (string) ($payload['run_key'] ?? ''),
        );
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

    public function handle(DeliveryReportService $reports): void
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $snapshot = $reports->summary($this->companyId, $from, $to);

        $filePath = sprintf(
            'delivery_reports/%s/%s_%s_%s.json',
            $this->companyId,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $this->runKey,
        );

        Storage::disk('local')->put(
            $filePath,
            (string) json_encode(['generated_at' => now()->toIso8601String(), 'report' => $snapshot], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        Log::info('delivery.report.exported', [
            'job' => static::class,
            'job_id' => (string) ($this->job?->getJobId() ?? ''),
            'company_id' => $this->companyId,
            'from' => $this->from,
            'to' => $this->to,
            'run_key' => $this->runKey,
            'file' => $filePath,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('delivery.job.failed', [
            'job' => static::class,
            'job_id' => (string) ($this->job?->getJobId() ?? ''),
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
        ]);

        DeliveryDeadLetter::query()->withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'job_class' => static::class,
            'payload' => [
                'company_id' => $this->companyId,
                'from' => $this->from,
                'to' => $this->to,
                'run_key' => $this->runKey,
            ],
            'queue' => $this->queue ?? 'delivery',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'status' => 'new',
        ]);
    }
}
