<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Vtc\Application\Services\VtcDispatchService;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Entrée de la cascade de dispatch (BC-34 VTC, VTC-04/#8360).
 *
 * Enfilé par StartVtcDispatchListener sur VtcRideRequested : délègue à
 * VtcDispatchService::offerToNextCandidate (matching chauffeur disponible le
 * plus proche via le core geo BC-33). Idempotent : une course sortie de
 * `dispatching` (annulée, acceptée, expirée) est ignorée sans effet.
 * Tenant-scoped (EnsureTenantContext — spec ops : `queue:work --queue=vtc`).
 */
final class OfferRideToNearestDriverJob implements ShouldQueue, TenantScopedJob
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
        /** @var VtcRide|null $ride */
        $ride = VtcRide::query()->whereKey($this->rideId)->first();

        if (! $ride instanceof VtcRide) {
            return;
        }

        $dispatch->offerToNextCandidate($ride);
    }
}
