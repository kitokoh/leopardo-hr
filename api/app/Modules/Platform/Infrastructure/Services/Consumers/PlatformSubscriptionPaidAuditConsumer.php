<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services\Consumers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Platform\Domain\Contracts\PlatformOutboxConsumer;
use App\Modules\Platform\Domain\Exceptions\PermanentOutboxException;
use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;

/**
 * #5866 — Consommateur `platform.subscription.paid` (MAT-008).
 *
 * Trace l'encaissement d'un abonnement plateforme dans la piste d'audit
 * (action `outbox.subscription_paid`, auditable = événement outbox) —
 * preuve de délivrance de l'outbox et horizon de recouvrement. Idempotent :
 * l'écriture est sautée si un audit porte déjà le même `event_id`.
 */
final class PlatformSubscriptionPaidAuditConsumer implements PlatformOutboxConsumer
{
    public const EVENT_TYPE = 'platform.subscription.paid';

    public const AUDIT_ACTION = 'outbox.subscription_paid';

    public function supports(string $eventType): bool
    {
        return $eventType === self::EVENT_TYPE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $companyId = (string) ($payload['company_id'] ?? '');
        $eventId = (string) ($payload['event_id'] ?? '');
        $outboxEventId = (int) ($payload['outbox_event_id'] ?? 0);

        if ($companyId === '' || $eventId === '') {
            throw new PermanentOutboxException('payload incomplet : company_id et event_id requis');
        }

        $already = AuditLog::query()
            ->where('company_id', $companyId)
            ->where('action', self::AUDIT_ACTION)
            ->where('metadata->event_id', $eventId)
            ->exists();

        if ($already) {
            return;
        }

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => null,
            'action' => self::AUDIT_ACTION,
            'module' => 'billing',
            'auditable_type' => PlatformOutboxEvent::class,
            'auditable_id' => $outboxEventId > 0 ? (string) $outboxEventId : null,
            'old_values' => null,
            'new_values' => [
                'amount' => $payload['amount'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'payment_status' => $payload['status'] ?? null,
            ],
            'metadata' => [
                'event_id' => $eventId,
                'outbox' => true,
            ],
        ]);
    }
}
