<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-503 (#6202) — Réceptions de marchandises : entrées stock valorisées.
 *
 * Crée la réception (référence unique par tenant, `RCV-*` auto-générée ou
 * fournie), puis applique les entrées de stock via
 * `RestaurantStockMovementService` avec la raison `receiving` et la référence
 * de la réception. Le service recalcule le **coût moyen pondéré** à chaque
 * entrée (`avg_cost_minor` du niveau de stock) — invariant RESTO-503.
 *
 * Idempotence : la contrainte `UNIQUE(company_id, reference)` empêche la
 * double réception ; l'application des mouvements est transactionnelle.
 */
final class RestaurantReceivingService
{
    public function __construct(
        private readonly RestaurantStockMovementService $stockMovements,
    ) {
    }

    /**
     * @param  array<int, array{ingredient_id: int, quantity: string, unit_price_minor: int}>  $items
     */
    public function receive(
        Employee $actor,
        int $branchId,
        array $items,
        ?int $purchaseOrderId = null,
        ?int $supplierId = null,
        ?string $reference = null,
        ?string $noteRedacted = null,
    ): RestaurantReceiving {
        return DB::transaction(function () use ($actor, $branchId, $items, $purchaseOrderId, $supplierId, $reference, $noteRedacted): RestaurantReceiving {
            /** @var RestaurantReceiving $receiving */
            $receiving = RestaurantReceiving::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $branchId,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_id' => $supplierId,
                'reference' => $reference,
                'note_redacted' => $noteRedacted,
            ]);

            foreach ($items as $line) {
                $this->stockMovements->apply(
                    companyId: $actor->company_id,
                    branchId: $branchId,
                    ingredientId: (int) $line['ingredient_id'],
                    quantityDelta: (string) $line['quantity'],
                    reason: StockMovementReason::RECEIVING,
                    referenceType: 'receiving',
                    referenceId: (int) $receiving->id,
                    noteRedacted: 'Réception '.$receiving->reference,
                    userId: (int) $actor->id,
                    unitPriceMinor: (int) $line['unit_price_minor'],
                );
            }

            return $receiving;
        });
    }
}
