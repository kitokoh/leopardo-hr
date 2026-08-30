<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Delivery\Domain\Models\DeliveryDeadLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clôture asynchrone d'une tournée (BC-26-D07, issue #6295).
 *
 * Le contrôleur reste synchrone (clôtures légères) ; ce job porte la clôture
 * volumineuse (pattern GenerateBankExportJob / Integration Runtime BC-14) :
 * tenant-scoped (EnsureTenantContext, contexte restauré en fin de job),
 * **idempotent** (DeliveryRouteService::close : déjà close → même tournée,
 * aucun recalcul), retry borné (3 tentatives, backoff 10/30/60 s), et
 * dead-letter enregistrée dans `delivery_dead_letters` après épuisement —
 * rejeu possible via `delivery:replay-dlq` sans doublon métier.
 */
final class CloseDeliveryRouteJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $routeId,
        public readonly string $companyId,
    ) {
        $this->onQueue('delivery');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            routeId: (int) ($payload['route_id'] ?? 0),
            companyId: (string) ($payload['company_id'] ?? ''),
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

    public function handle(\App\Modules\Delivery\Application\Services\DeliveryRouteService $routes): void
    {
        $route = $routes->close($this->routeId, $this->companyId);

        Log::info('delivery.route.closed', [
            'job' => static::class,
            'job_id' => (string) $this->job?->getJobId(),
            'company_id' => $this->companyId,
            'route_id' => $this->routeId,
            'status' => $route->status,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('delivery.job.failed', [
            'job' => static::class,
            'job_id' => (string) ($this->job?->getJobId() ?? ''),
            'company_id' => $this->companyId,
            'route_id' => $this->routeId,
            'error' => $exception->getMessage(),
        ]);

        DeliveryDeadLetter::query()->withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'job_class' => static::class,
            'payload' => ['route_id' => $this->routeId, 'company_id' => $this->companyId],
            'queue' => $this->queue ?? 'delivery',
            'error' => $exception->getMessage(),
            // attempts() n'est sûr qu'une fois le job pické (job non null).
            'attempts' => $this->job?->attempts() ?? 0,
            'status' => 'new',
        ]);
    }
}
