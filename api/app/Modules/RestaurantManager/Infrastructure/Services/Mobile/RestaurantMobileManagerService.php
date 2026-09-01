<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\Mobile;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Actions\ClosePosSessionAction;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;

/**
 * RESTO-803 (#6224) — App mobile gérant : KPIs, alertes stock, clôtures.
 *
 * Pilotage nomade : indicateurs du jour calculés côté serveur (jamais
 * agrégés côté client), alertes de seuil de stock, session de caisse
 * courante et clôture (délègue à ClosePosSessionAction, RESTO-401/#6188).
 * Tout est tenant-scope (scope global BelongsToCompany).
 */
final class RestaurantMobileManagerService
{
    public function __construct(private readonly ClosePosSessionAction $closePosSession)
    {
    }

    /**
     * KPIs du jour : chiffre d'affaires (commandes payées), nombre de
     * commandes, panier moyen, rotation des tables.
     *
     * @return array{today_revenue_minor: int, orders_count: int, avg_basket_minor: int, tables_opened_today: int, currency: string|null}
     */
    public function kpis(Employee $actor): array
    {
        $start = now()->startOfDay();
        $currency = null;

        $paidOrders = RestaurantOrder::query()
            ->whereIn('status', [OrderStatus::PAID->value, OrderStatus::CLOSED->value])
            ->where('updated_at', '>=', $start)
            ->get();

        $revenue = 0;
        foreach ($paidOrders as $order) {
            $revenue += (int) $order->total_minor;
            $currency = $order->currency;
        }

        $ordersCount = $paidOrders->count();
        $avgBasket = $ordersCount > 0 ? (int) round($revenue / $ordersCount) : 0;

        $tablesOpened = RestaurantTableSession::query()
            ->where('opened_at', '>=', $start)
            ->count();

        return [
            'today_revenue_minor' => $revenue,
            'orders_count' => $ordersCount,
            'avg_basket_minor' => $avgBasket,
            'tables_opened_today' => $tablesOpened,
            'currency' => $currency,
        ];
    }

    /**
     * Alertes stock : niveaux sous le seuil d'alerte (actif/approvisionné
     * en premier). Borné aux 100 plus critiques.
     *
     * @return list<array{id: int, ingredient: string|null, quantity: float, alert_threshold: float|null, branch_id: int|null}>
     */
    public function stockAlerts(Employee $actor): array
    {
        return RestaurantStockLevel::query()
            ->with(['ingredient'])
            ->whereNotNull('alert_threshold')
            ->whereColumn('quantity', '<=', 'alert_threshold')
            ->orderByRaw('(quantity - alert_threshold) asc')
            ->limit(100)
            ->get()
            ->map(fn (RestaurantStockLevel $level): array => [
                'id' => $level->id,
                'ingredient' => $level->ingredient?->name,
                'quantity' => (float) $level->quantity,
                'alert_threshold' => $level->alert_threshold !== null ? (float) $level->alert_threshold : null,
                'branch_id' => $level->branch_id,
            ])
            ->all();
    }

    public function currentPosSession(Employee $actor): ?RestaurantPosSession
    {
        return RestaurantPosSession::query()
            ->where('status', PosSessionStatus::OPEN->value)
            ->orderBy('opened_at')
            ->first();
    }

    /**
     * @param  array{counted_cash_minor: int, variance_reason?: string|null}  $data
     */
    public function closePosSession(Employee $actor, RestaurantPosSession $session, array $data): RestaurantPosSession
    {
        if ($session->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($actor->cannot('close', $session)) {
            abort(403);
        }

        return $this->closePosSession->close($actor, $session, $data);
    }
}
