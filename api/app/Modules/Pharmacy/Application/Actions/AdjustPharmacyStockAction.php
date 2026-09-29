<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyStockService;

/**
 * Cas d'usage : ajustement d'inventaire d'un lot (raison obligatoire,
 * delta signé, manager) — PHARMA-003 (#7800).
 *
 * Consommé par `POST /api/v1/pharmacy/stock/adjustments`
 * (PharmacyStockController::storeAdjustment). La Policy `create` sur les
 * mouvements reste au niveau interface ; l'Action porte le cas d'usage
 * nommable. Les gardes métier (lot jamais négatif, `expiry_writeoff`,
 * transaction) restent dans PharmacyStockService (Infrastructure) — la
 * transaction englobante côté contrôleur, redondante avec celle du
 * service, est absorbée ici.
 */
class AdjustPharmacyStockAction
{
    public function __construct(
        private readonly PharmacyStockService $stock,
    ) {}

    /**
     * @param  array{batch_id: int, quantity_delta: int, reason: string, type?: string|null}  $payload
     */
    public function execute(Employee $actor, array $payload): PharmacyBatch
    {
        return $this->stock->adjust(
            (string) $actor->company_id,
            $payload['batch_id'],
            $payload['quantity_delta'],
            $payload['reason'],
            $actor->id,
            $payload['type'] ?? 'adjustment',
        );
    }
}
