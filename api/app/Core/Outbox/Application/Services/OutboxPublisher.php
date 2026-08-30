<?php

declare(strict_types=1);

namespace App\Core\Outbox\Application\Services;

use App\Core\Outbox\Domain\Models\OutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * MAT-008 (#5866) — Publication d'événements dans l'outbox générique.
 *
 * À appeler APRÈS le commit de la transaction métier (jamais dedans) :
 * l'effet est d'abord persisté, puis consommé de façon asynchrone.
 *
 * Idempotence : clé dérivée du (event_type, payload) par défaut, ou fournie ;
 * les index uniques partiels (tenant / plateforme) dédupliquent les rejets —
 * un pic de re-publication ne crée jamais deux événements.
 */
final class OutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $eventType,
        array $payload,
        ?string $companyId = null,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): OutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
        // imbriquée (savepoint) — une violation unique PostgreSQL ABORTE la
        // transaction courante (25P02) sans savepoint (pattern #5741).
        $existing = $this->find($eventType, $key, $companyId);

        if ($existing instanceof OutboxEvent) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): OutboxEvent => OutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
                'status' => OutboxEvent::STATUS_PENDING,
                'attempts' => 0,
                'max_attempts' => OutboxEvent::MAX_ATTEMPTS,
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->find($eventType, $key, $companyId);
            if ($existing instanceof OutboxEvent) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function find(string $eventType, string $key, ?string $companyId): ?OutboxEvent
    {
        $query = OutboxEvent::query()
            ->where('event_type', $eventType)
            ->where('idempotency_key', $key);

        if ($companyId === null) {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $companyId);
        }

        /** @var OutboxEvent|null $event */
        $event = $query->first();

        return $event;
    }
}
