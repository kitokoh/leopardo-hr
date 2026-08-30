<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à la complétion d'un rapprochement de stock (FUEL-009, #5803).
 *
 * Contrat Accounting (FUEL-015) : `fuel.stock.reconciled.v1` avec la
 * variance et le statut (completed|exception). Notifications (FUEL-019) :
 * alerte écart quand le statut est `exception`.
 */
class FuelStockReconciliationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelStockReconciliation $reconciliation,
    ) {}
}
