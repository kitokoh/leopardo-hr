<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelReconciliationReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis quand un rapport de rapprochement stock est produit/recalculé
 * (FUEL-009, #5803).
 *
 * Contrats aval (FUEL-015/017) : un listener Accounting peut consommer les
 * écarts validés ; le reporting peut re-calculer ses read models sans
 * rejouer tout l'historique. L'état est figé dans l'événement
 * (status/variance calculés serveur).
 */
class FuelReconciliationReported
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelReconciliationReport $report,
    ) {}
}
