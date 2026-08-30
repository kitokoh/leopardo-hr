<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Publication d'événements dans l'outbox FuelStation (FUEL-015, #5809).
 *
 * À appeler APRÈS le commit de la transaction métier (jamais dedans) :
 * l'effet est d'abord persisté, puis consommé de façon asynchrone.
 * Idempotence : clé dérivée du (event_type, aggregate) par défaut, ou
 * fournie ; la contrainte unique (company_id, idempotency_key) déduplique
 * les rejets. Pattern aligné sur l'outbox CRM (#5741).
 */
final class FuelOutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?int $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): FuelOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
        // imbriquée (savepoint). Une violation unique PostgreSQL ABORTE la
        // transaction courante (25P02) : sans savepoint, le SELECT du catch
        // échoue en cascade (leçon outbox CRM #5741).
        $existing = FuelOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof FuelOutboxEvent) {
            return $existing;
        }

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
            /** @var FuelOutboxEvent $existing */
            $existing = FuelOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }
}
