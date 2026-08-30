<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-404 (#6191) — Publication d'événements dans l'outbox RestaurantManager
 * (pattern `restaurant_outbox_events`, #6178 — calqué sur CrmOutboxPublisher
 * #5741).
 *
 * À appeler APRÈS le commit de la transaction métier (jamais dedans) : l'effet
 * est d'abord persisté, puis consommé de façon asynchrone et idempotente.
 * Idempotence : clé dérivée (event_type, payload) par défaut, ou fournie ;
 * la contrainte unique (company_id, idempotency_key) déduplique les rejets.
 */
final class RestaurantOutboxPublisher
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
    ): RestaurantOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
        // imbriquée (savepoint). Une violation unique PostgreSQL ABORTE la
        // transaction courante (25P02) : sans savepoint, le SELECT du catch
        // échoue en cascade (observé en CI sur le test « double publish »).
        $existing = RestaurantOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof RestaurantOutboxEvent) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): RestaurantOutboxEvent => RestaurantOutboxEvent::query()->create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'payload_redacted' => $payload,
                'status' => 'pending',
                'idempotency_key' => $key,
                'available_at' => $availableAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var RestaurantOutboxEvent $existing */
            $existing = RestaurantOutboxEvent::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }
}
