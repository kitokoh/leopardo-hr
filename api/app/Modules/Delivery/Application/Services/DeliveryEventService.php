<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Services;

use App\Modules\Delivery\Domain\Enums\DeliveryEventType;
use App\Modules\Delivery\Domain\Enums\DeliveryStatus;
use App\Modules\Delivery\Domain\Exceptions\InvalidDeliveryTransitionException;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement des événements de tracking (DELIVERY-204, issue #6288).
 *
 * - **Idempotent** : unique `(company_id, delivery_id, type, event_at)` et
 *   `idempotency_key` client — un rejeu retourne l'événement existant, jamais
 *   un doublon.
 * - **Transitions** : chaque type d'événement est une transition légale de la
 *   machine à états (picked_up → out_for_delivery → arrived → delivered) ;
 *   `delivered` exige une POD (proof_document_id, BC-20 par valeur).
 * - **Concurrence** : livraison verrouillée `SELECT FOR UPDATE`.
 */
final class DeliveryEventService
{
    public function record(
        string $companyId,
        int $deliveryId,
        string $type,
        Carbon $eventAt,
        ?float $latitude,
        ?float $longitude,
        string $origin,
        ?string $idempotencyKey,
        ?int $proofDocumentId,
    ): DeliveryEvent {
        return DB::transaction(function () use (
            $companyId,
            $deliveryId,
            $type,
            $eventAt,
            $latitude,
            $longitude,
            $origin,
            $idempotencyKey,
            $proofDocumentId,
        ): DeliveryEvent {
            /** @var Delivery|null $delivery */
            $delivery = Delivery::query()
                ->where('company_id', $companyId)
                ->whereKey($deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                abort(404);
            }

            // Idempotence 1/2 : clé client — même événement rejoué → existant.
            if ($idempotencyKey !== null) {
                $existing = DeliveryEvent::query()
                    ->where('company_id', $companyId)
                    ->where('delivery_id', $deliveryId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            // Idempotence 2/2 : unique (company, delivery, type, event_at) —
            // couvre le rejeu sans clé client et les doublons d'horodatage.
            $existing = DeliveryEvent::query()
                ->where('company_id', $companyId)
                ->where('delivery_id', $deliveryId)
                ->where('type', $type)
                ->where('event_at', $eventAt)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // POD obligatoire pour `delivered` (machine à états, refus explicite).
            $delivered = $type === DeliveryEventType::Delivered->value;
            if ($delivered && $proofDocumentId === null) {
                abort(409, 'PROOF_REQUIRED');
            }

            try {
                $delivery->transitionTo(DeliveryStatus::from($type), hasProof: $delivered);
                $delivery->save();
            } catch (InvalidDeliveryTransitionException) {
                abort(409, 'INVALID_TRANSITION');
            }

            /** @var DeliveryEvent $event */
            $event = DeliveryEvent::query()->create([
                'company_id' => $companyId,
                'delivery_id' => $deliveryId,
                'type' => $type,
                'event_at' => $eventAt,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'origin' => $origin,
                'idempotency_key' => $idempotencyKey,
                'payload' => $proofDocumentId !== null ? ['proof_document_id' => $proofDocumentId] : null,
            ]);

            return $event;
        });
    }
}
