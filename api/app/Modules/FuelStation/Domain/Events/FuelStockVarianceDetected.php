<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis quand un rapprochement de stock se clôt en `exception` (FUEL-009,
 * #5803) — variance au-delà de la tolérance, aucun ajustement silencieux.
 *
 * Consommé par FUEL-019 (alerte écart de stock dédupliquée) et par le
 * contrat Accounting FUEL-015 (flux `fuel.stock.variance.v1`).
 */
class FuelStockVarianceDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelStockReconciliation $reconciliation,
    ) {}
}
