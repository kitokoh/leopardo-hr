<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Services;

use App\Modules\Delivery\Domain\Enums\DeliveryStatus;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie des tournées (DELIVERY-202, issue #6286).
 *
 * - `create` : tournée draft + stops ordonnés, colis validés (tenant,
 *   pas déjà dans une tournée non close).
 * - `assign` : affectation livreur/véhicule — **idempotente** (même
 *   livreur+véhicule → même résultat) et garde anti-chevauchement
 *   (1 livreur = 1 tournée par date, verrou `SELECT FOR UPDATE` + index
 *   unique `delivery_routes_company_date_driver_unique`).
 * - `close` : clôture **idempotente** — refuse une tournée avec des stops
 *   non terminés, calcule les totaux dénormalisés depuis les stops
 *   (livré/échec/COD collecté), verrouillage `SELECT FOR UPDATE`.
 *
 * Toutes les opérations sont scopées `company_id` (fail-closed #3727).
 */
final class DeliveryRouteService
{
    /**
     * Statuts terminaux d'un stop (clôture possible).
     *
     * @var list<string>
     */
    private const TERMINAL_STOP_STATUSES = ['delivered', 'failed', 'skipped'];

    /** États terminaux d'une livraison (machine à états, DELIVERY-103). */
    private const TERMINAL_DELIVERY_STATUSES = ['delivered', 'failed', 'returned', 'cancelled'];

    /**
     * Crée une tournée + ses stops ordonnés (statut `draft`).
     *
     * @param  list<int>  $deliveryIds
     */
    public function create(string $companyId, Carbon $routeDate, ?string $zone, array $deliveryIds): DeliveryRoute
    {
        return DB::transaction(function () use ($companyId, $routeDate, $zone, $deliveryIds): DeliveryRoute {
            $deliveries = Delivery::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $deliveryIds)
                ->get();

            if ($deliveries->count() !== count(array_unique($deliveryIds))) {
                abort(422, 'DELIVERY_NOT_FOUND_OR_FOREIGN');
            }

            // Un colis déjà planifié dans une tournée non close ne peut pas
            // être ré-affecté (les doublons d'arrêts sont interdits).
            $alreadyPlanned = DeliveryStop::query()
                ->where('company_id', $companyId)
                ->whereIn('delivery_id', $deliveryIds)
                ->whereHas('route', fn ($q) => $q->where('status', '!=', 'completed'))
                ->exists();

            if ($alreadyPlanned) {
                abort(409, 'DELIVERY_ALREADY_PLANNED');
            }

            /** @var DeliveryRoute $route */
            $route = DeliveryRoute::query()->create([
                'company_id' => $companyId,
                'route_date' => $routeDate->toDateString(),
                'zone' => $zone,
                'status' => 'draft',
            ]);

            $now = now();
            $stops = $deliveries
                ->sortBy(fn (Delivery $d) => array_search($d->id, $deliveryIds, true))
                ->values()
                ->map(fn (Delivery $delivery, int $index) => [
                    'company_id' => $companyId,
                    'route_id' => $route->id,
                    'delivery_id' => $delivery->id,
                    'sort_order' => $index + 1,
                    'status' => 'pending',
                    'address' => $delivery->dropoff_address,
                    'contact' => $delivery->dropoff_contact,
                    'phone' => $delivery->dropoff_phone,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DeliveryStop::query()->insert($stops);

            return $route->load('stops');
        });
    }

    /**
     * Affecte un livreur + véhicule — idempotent, garde anti-chevauchement.
     */
    public function assign(int $routeId, string $companyId, int $driverId, ?string $vehicleCode): DeliveryRoute
    {
        return DB::transaction(function () use ($routeId, $companyId, $driverId, $vehicleCode): DeliveryRoute {
            $route = $this->lockRoute($routeId, $companyId);

            // Une tournée close est immutable (idempotence stricte).
            if ($route->status === 'completed') {
                abort(409, 'ROUTE_CLOSED');
            }

            // Idempotence : même affectation → même tournée, aucun effet.
            if ($route->driver_id === $driverId && $route->vehicle_code === $vehicleCode) {
                return $route->load('stops');
            }

            // Anti-chevauchement : un livreur/véhicule = une tournée par date.
            $overlap = DeliveryRoute::query()
                ->where('company_id', $companyId)
                ->where('route_date', $route->route_date->toDateString())
                ->where('driver_id', $driverId)
                ->whereKeyNot($route->id)
                ->exists();

            if ($overlap) {
                abort(409, 'DRIVER_ALREADY_ASSIGNED');
            }

            $route->forceFill([
                'driver_id' => $driverId,
                'vehicle_code' => $vehicleCode,
                'status' => 'assigned',
            ])->save();

            // Les colis de la tournée passent à `assigned` (machine à états :
            // created → picked_up est illégal — l'affectation EST la transition).
            $route->stops()->with('delivery')->get()->each(function (DeliveryStop $stop): void {
                if ($stop->delivery !== null && $stop->delivery->status === 'created') {
                    $stop->delivery->transitionTo(DeliveryStatus::Assigned);
                }
            });

            return $route->load('stops');
        });
    }

    /**
     * Clôture une tournée — idempotente, refuse une tournée incomplète.
     */
    public function close(int $routeId, string $companyId): DeliveryRoute
    {
        return DB::transaction(function () use ($routeId, $companyId): DeliveryRoute {
            $route = $this->lockRoute($routeId, $companyId);

            // Idempotence : déjà close → même tournée, aucun recalcul.
            if ($route->status === 'completed') {
                return $route->load('stops');
            }

            $stops = $route->stops()->get();

            if ($stops->isEmpty()) {
                abort(409, 'ROUTE_EMPTY');
            }

            $openStops = $stops->whereNotIn('status', self::TERMINAL_STOP_STATUSES);

            // Un arrêt dont la LIVRAISON est en état terminal (delivered/
            // failed/returned/cancelled) est considéré terminé : le statut
            // des arrêts est porté par le mobile, celui des livraisons par
            // les événements — les deux chemins convergent ici (golden
            // journey : events seuls, sans update d'arrêt).
            if ($openStops->isNotEmpty()) {
                $terminalDeliveryIds = Delivery::query()
                    ->where('company_id', $companyId)
                    ->whereIn('status', self::TERMINAL_DELIVERY_STATUSES)
                    ->pluck('id');

                $openStops = $openStops->reject(
                    fn ($stop) => $terminalDeliveryIds->contains($stop->delivery_id)
                );
            }

            if ($openStops->isNotEmpty()) {
                abort(409, 'ROUTE_INCOMPLETE');
            }

            // Totaux : statut d'arrêt terminal OU statut de livraison terminal
            // (événements) — déterminisme : deux clôtures → mêmes résultats.
            $deliveries = Delivery::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $stops->pluck('delivery_id'))
                ->get()
                ->keyBy('id');

            $deliveredCount = $stops->filter(function ($stop) use ($deliveries): bool {
                if ($stop->status === 'delivered') {
                    return true;
                }

                return $deliveries->get($stop->delivery_id)?->status === 'delivered';
            })->count();

            $failedCount = $stops->filter(function ($stop) use ($deliveries): bool {
                if ($stop->status === 'failed') {
                    return true;
                }

                $deliveryStatus = $deliveries->get($stop->delivery_id)?->status;

                return in_array($deliveryStatus, ['failed', 'returned', 'cancelled'], true);
            })->count();

            $deliveredIds = $stops->filter(function ($stop) use ($deliveries): bool {
                if ($stop->status === 'delivered') {
                    return true;
                }

                return $deliveries->get($stop->delivery_id)?->status === 'delivered';
            })->pluck('delivery_id');

            $codCollected = (int) Delivery::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $deliveredIds)
                ->sum('cod_amount_minor');

            $route->forceFill([
                'deliveries_count' => $stops->count(),
                'delivered_count' => $deliveredCount,
                'failed_count' => $failedCount,
                'cod_collected_minor' => $codCollected,
                'status' => 'completed',
                'closed_at' => now(),
            ])->save();

            return $route->load('stops');
        });
    }

    private function lockRoute(int $routeId, string $companyId): DeliveryRoute
    {
        /** @var DeliveryRoute|null $route */
        $route = DeliveryRoute::query()
            ->where('company_id', $companyId)
            ->whereKey($routeId)
            ->lockForUpdate()
            ->first();

        if ($route === null) {
            abort(404);
        }

        return $route;
    }
}
