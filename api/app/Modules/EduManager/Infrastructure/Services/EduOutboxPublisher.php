<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Modules\EduManager\Domain\Models\EduOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5832 (EDU-016) — Publication d'événements dans l'outbox EduManager
 * (pattern `edu_outbox_events`, calqué sur RestaurantOutboxPublisher et
 * CrmOutboxPublisher #5741).
 *
 * À appeler APRÈS le commit de la transaction métier (jamais dedans) : l'effet
 * est d'abord persisté, puis consommé de façon asynchrone et idempotente par
 * `edu:outbox-dispatch`. Idempotence : clé dérivée (event_type, payload) par
 * défaut, ou fournie ; la contrainte unique (company_id, idempotency_key)
 * déduplique les rejets (savepoint pour ne pas aborter la transaction).
 */
final class EduOutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload  payload redigé (aucune PII)
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $availableAt = null,
    ): EduOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = EduOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof EduOutboxEvent) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): EduOutboxEvent => EduOutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => EduOutboxEvent::STATUS_PENDING,
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var EduOutboxEvent $existing */
            $existing = EduOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }
}
