<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Outbox\Domain\Contracts\OutboxConsumer;
use App\Core\Outbox\Domain\Models\OutboxEvent;
use Illuminate\Support\Facades\Log;

/**
 * MAT-008 (#5866) — Consommateur de référence des événements de plateforme.
 *
 * Persiste une trace d'audit par événement livré (action `outbox.delivered`)
 * dans `audit_logs`. La preuve de livraison est idempotente : le dispatcher
 * ne passe ici qu'une fois par lease, et l'audit reste rejouable sans
 * doublon grâce à la clé d'idempotence conservée dans le payload.
 *
 * Ce consommateur est le socle des futures intégrations plateforme
 * (webhooks sortants, notification des partenaires…) : tout consommateur
 * supplémentaire s'enregistre dans le registre.
 */
class PlatformEventOutboxConsumer implements OutboxConsumer
{
    private const SUPPORTED_TYPES = ['company.created', 'billing.subscription_paid'];

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED_TYPES, true);
    }

    public function handle(OutboxEvent $event): void
    {
        AuditLog::query()->create([
            'company_id' => $event->company_id,
            'user_id' => null,
            'action' => 'outbox.delivered',
            'module' => 'platform',
            'auditable_type' => $event->aggregate_type !== null ? 'outbox.'.$event->aggregate_type : null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'event_type' => $event->event_type,
                'idempotency_key' => $event->idempotency_key,
                'payload' => $event->payload,
            ],
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => [
                'category' => 'outbox',
                'outbox_event_id' => $event->id,
                'aggregate_id' => $event->aggregate_id,
                'status' => $event->status,
            ],
        ]);

        Log::info('outbox.delivered', [
            'outbox_event_id' => $event->id,
            'event_type' => $event->event_type,
            'idempotency_key' => $event->idempotency_key,
            'company_id' => $event->company_id,
        ]);
    }
}
