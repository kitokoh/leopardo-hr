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
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * FUEL-015 (#5809) — Publication d'événements dans l'outbox FuelStation.
 *
 * À appeler APRÈS le commit de la transaction métier (jamais dedans) :
 * l'effet métier est d'abord persisté, puis l'événement de synthèse est
 * consommé de façon asynchrone. Idempotence : clé dérivée du
 * (event_type, payload) par défaut, ou fournie ; la contrainte unique
 * (company_id, idempotency_key) déduplique les rejets.
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
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): FuelOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
        // imbriquée (savepoint). Une violation unique PostgreSQL ABORTE la
        // transaction courante (25P02) : sans savepoint, le SELECT du catch
        // échoue en cascade (pattern CRM #5741).
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
        try {
            return DB::transaction(fn (): FuelOutboxEvent => FuelOutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
                'status' => FuelOutboxEvent::STATUS_PENDING,
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var FuelOutboxEvent $existingAfterRace */
            $existingAfterRace = FuelOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existingAfterRace;
        }
    }
}
