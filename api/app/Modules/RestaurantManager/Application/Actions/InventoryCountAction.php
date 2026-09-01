<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\InventoryCountStatus;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Infrastructure\Services\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-504 (#6203) — Cycle de vie d'un inventaire physique.
 *
 * draft (attendu pré-rempli depuis les niveaux de stock) → saisie des
 * comptages → submit → approve (réservé manage). À l'approbation, chaque
 * écart non nul génère un mouvement `count` (delta = compté − attendu) :
 * le stock est ajusté atomiquement (verrou ligne). Un écart non justifié
 * (variance ≠ 0 sans `reason_code`) bloque l'approbation (422).
 */
final class InventoryCountAction
{
    public function __construct(private readonly StockMovementService $movements)
    {
    }

    public function submit(Employee $actor, RestaurantInventoryCount $count): RestaurantInventoryCount
    {
        if ($count->company_id !== $actor->company_id) {
            throw new RuntimeException('Inventory count does not belong to tenant.');
        }

        if ($count->status !== InventoryCountStatus::DRAFT) {
            abort(409, sprintf('Only a draft inventory count can be submitted (current status "%s").', $count->status->value));
        }

        $count->forceFill(['status' => InventoryCountStatus::SUBMITTED->value])->save();

        return $count;
    }

    public function approve(Employee $actor, RestaurantInventoryCount $count): RestaurantInventoryCount
    {
        if ($count->company_id !== $actor->company_id) {
            throw new RuntimeException('Inventory count does not belong to tenant.');
        }

        if ($count->status !== InventoryCountStatus::SUBMITTED) {
            abort(409, sprintf('Only a submitted inventory count can be approved (current status "%s").', $count->status->value));
        }

        $items = $count->items()->get();

        foreach ($items as $item) {
            $variance = (float) ($item->variance_qty ?? 0);

            if (abs($variance) > 0.0001 && empty($item->reason_code)) {
                abort(422, sprintf('Variance on ingredient %d requires a justification reason.', (int) $item->ingredient_id));
            }
        }

        DB::transaction(function () use ($count, $items, $actor): void {
            foreach ($items as $item) {
                $variance = (float) ($item->variance_qty ?? 0);

                if (abs($variance) <= 0.0001) {
                    continue;
                }

                $this->movements->apply(
                    companyId: $count->company_id,
                    branchId: $count->branch_id,
                    ingredientId: (int) $item->ingredient_id,
                    quantityDelta: $variance,
                    reason: StockMovementReason::COUNT,
                    referenceType: RestaurantInventoryCount::class,
                    referenceId: $count->id,
                    note: $item->reason_code,
                    userId: $actor->id,
                    allowNegative: true, // l'inventaire est la vérité terrain : corrige même les négatifs
                );
            }

            $count->forceFill([
                'status' => InventoryCountStatus::APPROVED->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
        });

        $count->refresh();

        return $count;
    }
}
