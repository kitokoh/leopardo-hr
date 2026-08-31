<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * #5866 — Publication d'événements dans l'outbox plateforme (MAT-008).
 *
 * À appeler au moment de l'événement métier (listener synchrone) : l'effet
 * est d'abord persisté, puis consommé de façon asynchrone et idempotente.
 * Idempotence : clé dérivée du (event_type, payload) par défaut, ou fournie ;
 * la contrainte unique (company_id, idempotency_key) déduplique les rejets.
 */
final class PlatformOutboxPublisher
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
    ): PlatformOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
        // imbriquée (savepoint). Une violation unique PostgreSQL ABORTE la
        // transaction courante (25P02) : sans savepoint, le SELECT du catch
        // échoue en cascade (même garde que l'outbox CRM #5741).
        $existing = PlatformOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof PlatformOutboxEvent) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): PlatformOutboxEvent => PlatformOutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
                'status' => PlatformOutboxEvent::STATUS_PENDING,
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var PlatformOutboxEvent $existing */
            $existing = PlatformOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }
}
