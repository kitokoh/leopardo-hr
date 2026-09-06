<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Delivery\Application\Services\DeliveryEventService;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use App\Modules\Delivery\Domain\Support\DeliveryRoleResolver;
use Illuminate\Database\ConnectionInterface;

/**
 * DELIVERY-203 (#6287) — changement de statut d'un arrêt de tournée par le
 * livreur (mobile). Extraite de DeliveryRiderController::status (couche
 * Application vide, #6898). Comportement conservé à l'identique :
 *  - verrou `SELECT FOR UPDATE` + scope tenant (fail-closed) ;
 *  - RBAC par propriété : seul le livreur assigné (ou dispatcher/manager/
 *    admin) opère l'arrêt ;
 *  - idempotence : même statut rejoué → même arrêt, aucun effet ;
 *  - chaque statut métier déclenche l'événement de tracking correspondant
 *    (idempotent + POD + machine à états — DeliveryEventService).
 */
final class UpdateDeliveryStopStatusAction
{
    public function __construct(
        private readonly DeliveryEventService $events,
        private readonly DeliveryRoleResolver $roles,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param  int|string|null  $proofDocumentId  POD obligatoire pour `delivered`
     */
    public function execute(
        int $stopId,
        string $companyId,
        Employee $employee,
        string $status,
        int|string|null $proofDocumentId,
    ): DeliveryStop {
        return $this->db->transaction(function () use ($stopId, $companyId, $employee, $status, $proofDocumentId): DeliveryStop {
            /** @var DeliveryStop|null $found */
            $found = DeliveryStop::query()
                ->where('company_id', $companyId)
                ->whereKey($stopId)
                ->lockForUpdate()
                ->first();

            if ($found === null) {
                abort(404);
            }

            $route = $found->route()->where('company_id', $companyId)->first();

            if ($route === null || ! $this->canOperate($employee, $route)) {
                abort(403, 'ROUTE_NOT_ASSIGNED_TO_RIDER');
            }

            // Idempotence : même statut rejoué → même arrêt, aucun effet.
            if ($found->status === $status) {
                return $found;
            }

            // Chaque statut métier déclenche l'événement de tracking correspondant
            // (idempotent + POD + machine à états — DeliveryEventService).
            $eventType = match ($status) {
                'en_route' => 'out_for_delivery',
                'arrived' => 'arrived',
                'delivered' => 'delivered',
                'failed' => 'failed',
                default => null, // skipped : pas d'événement de livraison
            };

            if ($eventType !== null) {
                $this->events->record(
                    companyId: $companyId,
                    deliveryId: (int) $found->delivery_id,
                    type: $eventType,
                    eventAt: now(),
                    latitude: null,
                    longitude: null,
                    origin: 'mobile',
                    idempotencyKey: null,
                    proofDocumentId: $eventType === 'delivered' ? $this->proofIdOrNull($proofDocumentId) : null,
                );
            }

            $now = now();

            $found->forceFill([
                'status' => $status,
                'arrived_at' => in_array($status, ['arrived', 'delivered'], true) ? $now : $found->arrived_at,
                'delivered_at' => $status === 'delivered' ? $now : null,
                'proof_id' => $status === 'delivered' ? $proofDocumentId : $found->proof_id,
            ])->save();

            $fresh = $found->fresh();
            if ($fresh === null) {
                abort(500, 'DELIVERY_STOP_RELOAD_FAILED');
            }

            return $fresh;
        });
    }

    private function canOperate(Employee $employee, DeliveryRoute $route): bool
    {
        if ($this->roles->hasAnyRole($employee, ['dispatcher', 'manager', 'admin'])) {
            return true;
        }

        return $route->driver_id !== null && (int) $route->driver_id === (int) $employee->id;
    }

    /**
     * Le POD arrive du payload validé (id numérique, souvent string) ; le
     * contrat DeliveryEventService attend int|null.
     */
    private function proofIdOrNull(int|string|null $proofDocumentId): ?int
    {
        if ($proofDocumentId === null || ! is_numeric($proofDocumentId)) {
            return null;
        }

        return (int) $proofDocumentId;
    }
}
