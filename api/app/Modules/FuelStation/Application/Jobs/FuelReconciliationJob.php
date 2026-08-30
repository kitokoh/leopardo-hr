<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Jobs;

use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * #5803 — Rapprochement stock d'une station/période (FUEL-009).
 *
 * Rejouable sans doublon : `FuelStockService::reconcile()` est un
 * updateOrCreate par (company_id, station_id, period) — un rejeu remplace le
 * rapport précédent, il ne duplique jamais. Le grand-livre des mouvements
 * est lui-même idempotent (clé unique idempotency_key).
 */
class FuelReconciliationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        private readonly string $companyId,
        private readonly int $stationId,
        private readonly string $period,
    ) {
    }

    public function handle(FuelStockService $stocks): void
    {
        $stocks->reconcile($this->companyId, $this->stationId, $this->period);
    }

    public function companyId(): string
    {
        return $this->companyId;
    }

    public function stationId(): int
    {
        return $this->stationId;
    }

    public function period(): string
    {
        return $this->period;
    }
}
