<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacySaleService;

/**
 * Cas d'usage : annulation (void) d'une vente comptoir par un manager —
 * PHARMA-005 (#7802).
 *
 * Consommé par `POST /api/v1/pharmacy/sales/{sale}/void`
 * (PharmacySaleController::void). La Policy `void` reste au niveau
 * interface ; l'Action porte le cas d'usage nommable. La vente est
 * conservée et le stock ré-crédité par mouvements `return` dans
 * PharmacySaleService (Infrastructure).
 */
class VoidPharmacySaleAction
{
    public function __construct(
        private readonly PharmacySaleService $sales,
    ) {}

    public function execute(PharmacySale $sale, string $reason, Employee $actor): PharmacySale
    {
        return $this->sales->void($sale, $reason, $actor->id);
    }
}
