<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Support\Str;

/**
 * Publication d'événements FuelStation dans l'outbox (FUEL-015/019).
 *
 * Persistance APRÈS le commit métier, consommation asynchrone idempotente
 * par `fuel:outbox-dispatch`. `idempotency_key` unique par tenant → un
 * rejeu du même fait métier ne crée jamais de doublon.
 */
final class FuelOutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $companyId, string $eventType, array $payload, ?string $aggregateType = null, ?string $aggregateId = null, ?string $idempotencyKey = null): FuelOutboxEvent
    {
        $key = $idempotencyKey ?? $this->makeKey($eventType, $aggregateType, $aggregateId);

        /** @var FuelOutboxEvent|null $existing */
        $existing = FuelOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof FuelOutboxEvent) {
            return $existing;
        }

        return FuelOutboxEvent::query()->create([
            'company_id' => $companyId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => array_merge($payload, ['_event_type' => $eventType]),
            'idempotency_key' => $key,
            // available_at explicite (tronqué à la seconde par le binding
            // Laravel) : un événement est dû dès sa seconde d'insertion —
            // le CURRENT_TIMESTAMP µs du défaut DB le rendait non-dû pendant
            // la même seconde (claim `available_at <= now()` faux).
            'available_at' => now(),
        ]);
    }

    private function makeKey(string $eventType, ?string $aggregateType, ?string $aggregateId): string
    {
        return $eventType.'-'.($aggregateType ?? 'none').'-'.($aggregateId ?? Str::uuid()->toString());
    }
}
