<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;

/**
 * RESTO-505 (#6204) — Alertes de seuil de stock.
 *
 * Détecte les ingrédients passés sous leur seuil d'alerte
 * (`alert_threshold`, ou `reorder_level` en repli) et publie
 * `restaurant.stock.alert.v1` via l'outbox. Anti-spam : une seule alerte par
 * (branche, ingrédient, jour) — clé d'idempotence dérivée de la période
 * (consommateurs : Notifications gérant, Reporting, spec §6.3).
 */
final class StockAlertService
{
    public const EVENT_STOCK_ALERT = 'restaurant.stock.alert.v1';

    public function __construct(private readonly RestaurantOutboxPublisher $outbox)
    {
    }

    /**
     * Vérifie un niveau de stock précis (appel après chaque mouvement).
     */
    public function checkLevel(RestaurantStockLevel $level): void
    {
        $threshold = $level->alert_threshold ?? $level->reorder_level;

        if ($threshold === null) {
            return;
        }

        if ((float) $level->quantity > (float) $threshold) {
            return;
        }

        $this->publishAlert($level);
    }

    /**
     * Rescan complet d'une branche (ou de tout le tenant si branchId null) —
     * utilisé par la commande `leopardo:restaurant:stock-alerts`.
     */
    public function scan(string $companyId, ?int $branchId = null): int
    {
        $query = RestaurantStockLevel::query()
            ->where('company_id', $companyId)
            ->whereNotNull('alert_threshold')
            ->orWhereNotNull('reorder_level');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $published = 0;

        $query->get()->each(function (RestaurantStockLevel $level) use (&$published): void {
            $threshold = $level->alert_threshold ?? $level->reorder_level;

            if ($threshold === null || (float) $level->quantity > (float) $threshold) {
                return;
            }

            $this->publishAlert($level);
            $published++;
        });

        return $published;
    }

    private function publishAlert(RestaurantStockLevel $level): void
    {
        // Une alerte par (branche, ingrédient, jour) — pas de spam (critère
        // d'acceptation) : la clé dérivée rend l'outbox idempotente.
        $periodKey = $level->updated_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        $this->outbox->publish(
            $level->company_id,
            self::EVENT_STOCK_ALERT,
            [
                'stock_level_id' => $level->id,
                'branch_id' => $level->branch_id,
                'ingredient_id' => $level->ingredient_id,
                'quantity' => (float) $level->quantity,
                'alert_threshold' => (float) ($level->alert_threshold ?? $level->reorder_level),
                'currency' => null,
            ],
            idempotencyKey: hash('sha256', sprintf('stock-alert:%s:%d:%d:%s', $level->company_id, $level->branch_id, $level->ingredient_id, $periodKey)),
        );
    }
}
