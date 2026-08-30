<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-310 (#6040) — Publication d'événements dans l'outbox TravelAgency.
 *
 * Miroir du pattern `CrmOutboxPublisher` (#5741) : à appeler APRÈS le
 * commit de la transaction métier (jamais dedans). Idempotence : clé
 * dérivée de (event_type, payload), contrainte unique (company_id,
 * idempotency_key) → rejeu sans doublon.
 */
final class TravelOutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $availableAt = null,
    ): TravelOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = TravelOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof TravelOutboxEvent) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): TravelOutboxEvent => TravelOutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'payload_redacted' => $payload,
                'status' => TravelOutboxEvent::STATUS_PENDING,
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var TravelOutboxEvent $existing */
            $existing = TravelOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }
}
