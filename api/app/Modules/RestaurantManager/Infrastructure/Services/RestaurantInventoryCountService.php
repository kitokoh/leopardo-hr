<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\InventoryCountStatus;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-504 (#6203) — Inventaires physiques : comptage, écarts, approbation.
 *
 * Cycle : création (lignes pré-remplies avec les quantités attendues depuis
 * les niveaux de stock) → saisie des comptés (écart calculé) → soumission →
 * approbation (manage). L'approbation applique les ajustements de stock
 * (mouvements raison `count`, référence de l'inventaire) et exige une
 * justification (`reason_code`) pour chaque écart non nul — un écart non
 * justifié bloque l'approbation (critère d'acceptation RESTO-504).
 */
final class RestaurantInventoryCountService
{
    public function __construct(
        private readonly RestaurantStockMovementService $stockMovements,
    ) {
    }

    /**
     * Crée un inventaire pré-rempli avec les quantités attendues (stock courant).
     */
    public function createWithExpected(Employee $actor, int $branchId): RestaurantInventoryCount
    {
        /** @var RestaurantInventoryCount $count */
        $count = RestaurantInventoryCount::query()->create([
            'company_id' => $actor->company_id,
            'branch_id' => $branchId,
            'counted_by_user_id' => (int) $actor->id,
            'status' => InventoryCountStatus::DRAFT,
        ]);

        $levels = RestaurantStockLevel::query()
            ->where('company_id', $actor->company_id)
            ->where('branch_id', $branchId)
            ->get();

        foreach ($levels as $level) {
            RestaurantInventoryCountItem::query()->create([
                'company_id' => $actor->company_id,
                'count_id' => $count->id,
                'ingredient_id' => $level->ingredient_id,
                'expected_qty' => (string) $level->quantity,
            ]);
        }

        return $count->load('items');
    }

    /**
     * Saisit une quantité comptée et recalcule l'écart (variance_qty).
     */
    public function recordCounted(RestaurantInventoryCountItem $item, string $countedQty, ?string $reasonCode = null): RestaurantInventoryCountItem
    {
        $expected = $item->expected_qty ?? '0';
        $variance = bcsub($countedQty, (string) $expected, 3);

        $item->counted_qty = $countedQty;
        $item->variance_qty = $variance;
        $item->reason_code = $reasonCode;
        $item->save();

        return $item;
    }

    /**
     * Soumission de l'inventaire (draft → submitted).
     */
    public function submit(RestaurantInventoryCount $count, Employee $actor): RestaurantInventoryCount
    {
        if ($count->status !== InventoryCountStatus::DRAFT) {
            throw new RuntimeException('Seul un inventaire en brouillon peut être soumis.');
        }

        $count->status = InventoryCountStatus::SUBMITTED;
        $count->save();

        return $count->load('items');
    }

    /**
     * Approbation : ajuste le stock pour chaque écart justifié, bloque sinon.
     */
    public function approve(RestaurantInventoryCount $count, Employee $actor): RestaurantInventoryCount
    {
        if ($count->status !== InventoryCountStatus::SUBMITTED) {
            throw new RuntimeException('Seul un inventaire soumis peut être approuvé.');
        }

        $unjustified = $count->items()
            ->whereNotNull('variance_qty')
            ->get()
            ->filter(fn (RestaurantInventoryCountItem $item) => bccomp((string) $item->variance_qty, '0', 3) !== 0 && $item->reason_code === null);

        if ($unjustified->isNotEmpty()) {
            $ingredientIds = $unjustified->map(fn ($item) => $item->ingredient_id)->implode(', ');
            throw new RuntimeException('Écart non justifié : raison requise pour les ingrédients #'.$ingredientIds.'.');
        }

        DB::transaction(function () use ($count, $actor): void {
            foreach ($count->items()->whereNotNull('variance_qty')->get() as $item) {
                if (bccomp((string) $item->variance_qty, '0', 3) === 0) {
                    continue;
                }

                $this->applyAdjustment($count, $item, $actor);
            }

            $count->status = InventoryCountStatus::APPROVED;
            $count->approved_by = (int) $actor->id;
            $count->approved_at = now();
            $count->save();
        });

        return $count->load('items');
    }

    private function applyAdjustment(RestaurantInventoryCount $count, RestaurantInventoryCountItem $item, Employee $actor): void
    {
        $this->stockMovements->apply(
            companyId: $count->company_id,
            branchId: $count->branch_id,
            ingredientId: $item->ingredient_id,
            quantityDelta: (string) $item->variance_qty,
            reason: StockMovementReason::COUNT,
            referenceType: 'inventory_count',
            referenceId: (int) $count->id,
            noteRedacted: 'Ajustement inventaire #'.$count->id,
            userId: (int) $actor->id,
        );
    }
}
