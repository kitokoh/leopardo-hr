<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Support\Carbon;

/**
 * RESTO-505 (#6204) — Alertes de seuil de stock.
 *
 * Détecte les niveaux de stock sous le seuil (`alert_threshold`) et publie
 * un événement `restaurant.stock.alert.v1` dans l'outbox (pattern #6178).
 *
 * Anti-spam (critère d'acceptation) : l'`idempotency_key` est
 * `stock-alert:{branch}:{ingredient}:{jour}` — `firstOrCreate` garantit
 * **une alerte par ingrédient/branche/période** (rejeu du job sans doublon).
 */
final class RestaurantStockAlertService
{
    /**
     * Scanne une company et publie les alertes franchies pour aujourd'hui.
     *
     * @return array{company_id: string, alerts_created: int, alerts_duplicates: int}
     */
    public function scanCompany(Company $company, ?int $branchId = null): array
    {
        $query = RestaurantStockLevel::query()
            ->where('company_id', $company->id)
            ->whereNotNull('alert_threshold')
            ->whereColumn('quantity', '<=', 'alert_threshold');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $day = Carbon::today()->toDateString();
        $created = 0;
        $duplicates = 0;

        foreach ($query->get() as $level) {
            $idempotencyKey = sprintf('stock-alert:%d:%d:%s', $level->branch_id, $level->ingredient_id, $day);

            $event = RestaurantOutboxEvent::query()->firstOrCreate(
                ['company_id' => $company->id, 'idempotency_key' => $idempotencyKey],
                [
                    'event_type' => 'restaurant.stock.alert.v1',
                    'payload_redacted' => [
                        'branch_id' => $level->branch_id,
                        'ingredient_id' => $level->ingredient_id,
                        'quantity' => $level->quantity,
                        'alert_threshold' => $level->alert_threshold,
                    ],
                    'status' => RestaurantOutboxEvent::STATUS_PENDING,
                    'attempts' => 0,
                    'available_at' => now(),
                ],
            );

            $event->wasRecentlyCreated ? $created++ : $duplicates++;
        }

        return [
            'company_id' => $company->id,
            'alerts_created' => $created,
            'alerts_duplicates' => $duplicates,
        ];
    }

    /**
     * Liste lecture des niveaux sous le seuil (endpoint GET /stock/alerts).
     *
     * @return \Illuminate\Support\Collection<int, RestaurantStockLevel>
     */
    public function belowThreshold(string $companyId, ?int $branchId = null): \Illuminate\Support\Collection
    {
        $query = RestaurantStockLevel::query()
            ->where('company_id', $companyId)
            ->whereNotNull('alert_threshold')
            ->whereColumn('quantity', '<=', 'alert_threshold');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('ingredient_id')->get();
    }
}
