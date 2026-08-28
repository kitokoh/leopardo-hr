<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * #5741 — Publication d'événements dans l'outbox CRM.
 *
 * À appeler APRÈS le commit de la transaction métier (jamais dedans) :
 * l'effet est d'abord persisté, puis consommé de façon asynchrone.
 * Idempotence : clé dérivée du (event_type, payload) par défaut, ou fournie ;
 * la contrainte unique (company_id, idempotency_key) déduplique les rejets.
 */
final class CrmOutboxPublisher
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
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): CrmOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
        // imbriquée (savepoint). Une violation unique PostgreSQL ABORTE la
        // transaction courante (25P02) : sans savepoint, le SELECT du catch
        // échoue en cascade (observé en CI sur le test « double publish »).
        $existing = CrmOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof CrmOutboxEvent) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): CrmOutboxEvent => CrmOutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
                'status' => CrmOutboxEvent::STATUS_PENDING,
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var CrmOutboxEvent $existing */
            $existing = CrmOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }
}
