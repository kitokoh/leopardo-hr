<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Services;

use App\Modules\Delivery\Domain\Contracts\DeliveryAccountingContract;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie des règlements COD (DELIVERY-205, issue #6289).
 *
 * `pending → collected → settled → reconciled`, chaque étape **idempotente**
 * (rejeu du même état → même règlement, aucun effet de bord) et verrouillée
 * `SELECT FOR UPDATE`. Le posting comptable passe par le contrat BC-08
 * (`DeliveryAccountingContract`) — une seule écriture par règlement.
 */
final class DeliveryCodSettlementService
{
    public function __construct(private readonly DeliveryAccountingContract $accounting) {}

    /**
     * Crée le règlement d'une tournée close — idempotent (unique
     * `(company_id, route_id)`).
     */
    public function createForRoute(int $routeId, string $companyId): DeliveryCodSettlement
    {
        return DB::transaction(function () use ($routeId, $companyId): DeliveryCodSettlement {
            /** @var DeliveryRoute|null $route */
            $route = DeliveryRoute::query()
                ->where('company_id', $companyId)
                ->whereKey($routeId)
                ->lockForUpdate()
                ->first();

            if ($route === null) {
                abort(404);
            }

            // Idempotence : règlement déjà créé → retourné tel quel.
            $existing = DeliveryCodSettlement::query()
                ->where('company_id', $companyId)
                ->where('route_id', $routeId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($route->status !== 'completed') {
                abort(409, 'ROUTE_NOT_CLOSED');
            }

            /** @var DeliveryCodSettlement $settlement */
            $settlement = DeliveryCodSettlement::query()->create([
                'company_id' => $companyId,
                'route_id' => $routeId,
                'driver_id' => $route->driver_id,
                'expected_minor' => $route->cod_collected_minor,
                'collected_minor' => 0,
                'commission_minor' => 0,
                'status' => 'pending',
            ]);

            return $settlement;
        });
    }

    /**
     * Remise caisse du livreur — idempotente, plafonnée à l'attendu.
     */
    public function collect(
        int $settlementId,
        string $companyId,
        int $collectedMinor,
        ?int $commissionMinor,
    ): DeliveryCodSettlement {
        return DB::transaction(function () use ($settlementId, $companyId, $collectedMinor, $commissionMinor): DeliveryCodSettlement {
            $settlement = $this->lockSettlement($settlementId, $companyId);

            // Idempotence : même remise rejouée → même règlement.
            if ($settlement->status === 'collected' && (int) $settlement->collected_minor === $collectedMinor) {
                return $settlement;
            }

            if ($settlement->status !== 'pending') {
                abort(409, 'SETTLEMENT_NOT_PENDING');
            }

            if ($collectedMinor > (int) $settlement->expected_minor) {
                abort(422, 'OVER_COLLECTION');
            }

            $settlement->forceFill([
                'collected_minor' => $collectedMinor,
                'commission_minor' => $commissionMinor ?? 0,
                'status' => 'collected',
                'collected_at' => now(),
            ])->save();

            return $settlement->fresh();
        });
    }

    /**
     * Posting BC-08 + passage à `settled` — idempotent.
     */
    public function settle(int $settlementId, string $companyId): DeliveryCodSettlement
    {
        return DB::transaction(function () use ($settlementId, $companyId): DeliveryCodSettlement {
            $settlement = $this->lockSettlement($settlementId, $companyId);

            if ($settlement->status === 'settled') {
                return $settlement;
            }

            if ($settlement->status !== 'collected') {
                abort(409, 'SETTLEMENT_NOT_COLLECTED');
            }

            $accountingRef = $this->accounting->postCodSettlement(
                $companyId,
                $settlementId,
                (int) $settlement->collected_minor,
                (int) $settlement->commission_minor,
            );

            $settlement->forceFill([
                'accounting_ref' => $accountingRef,
                'status' => 'settled',
                'settled_at' => now(),
            ])->save();

            return $settlement->fresh();
        });
    }

    /**
     * Réconciliation — idempotente, exige `settled`.
     */
    public function reconcile(int $settlementId, string $companyId): DeliveryCodSettlement
    {
        return DB::transaction(function () use ($settlementId, $companyId): DeliveryCodSettlement {
            $settlement = $this->lockSettlement($settlementId, $companyId);

            if ($settlement->status === 'reconciled') {
                return $settlement;
            }

            if ($settlement->status !== 'settled') {
                abort(409, 'SETTLEMENT_NOT_SETTLED');
            }

            $settlement->forceFill(['status' => 'reconciled'])->save();

            return $settlement->fresh();
        });
    }

    private function lockSettlement(int $settlementId, string $companyId): DeliveryCodSettlement
    {
        /** @var DeliveryCodSettlement|null $settlement */
        $settlement = DeliveryCodSettlement::query()
            ->where('company_id', $companyId)
            ->whereKey($settlementId)
            ->lockForUpdate()
            ->first();

        if ($settlement === null) {
            abort(404);
        }

        return $settlement;
    }
}
