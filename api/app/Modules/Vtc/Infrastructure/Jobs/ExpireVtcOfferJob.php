<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Vtc\Application\Services\VtcDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Timeout d'une offre de dispatch (BC-34 VTC, VTC-04/#8360).
 *
 * Programmé avec délai (`vtc.dispatch.offer_timeout_s`, défaut 30 s) à
 * chaque offre envoyée : consomme l'offre SI elle est encore la courante
 * (idempotent — un rejeu, une acceptation ou une déclinaison entre-temps
 * rendent ce job sans effet) puis enchaîne sur le candidat suivant.
 * Tenant-scoped (EnsureTenantContext).
 */
final class ExpireVtcOfferJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly string $companyId,
        public readonly int $rideId,
        public readonly int $driverId,
        public readonly int $offerSeq,
    ) {
        $this->onQueue('vtc');
    }

    public function tenantCompanyId(): string
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

    public function handle(VtcDispatchService $dispatch): void
    {
        $dispatch->expireOffer($this->companyId, $this->rideId, $this->driverId, $this->offerSeq);
    }
}
