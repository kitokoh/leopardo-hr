<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Services;

use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryEvent;
use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use App\Modules\Delivery\Domain\Models\DeliveryRecipientOptOut;

/**
 * Notifications destinataire (DELIVERY-206, issue #6290).
 *
 * - Déclenchement : chaque événement de tracking mappe un template versionné ;
 * - opt-out : un numéro opt-out arrête les notifications PLANIFIÉES ;
 * - outbox : `delivery_notifications` (pending → job → sent/failed), retry
 *   borné (3), aucune PII dans les logs.
 */
final class DeliveryNotificationService
{
    /**
     * Templates versionnés par type d'événement (clés BC-13 COMMS).
     *
     * @var array<string, string>
     */
    private const TEMPLATES = [
        'picked_up' => 'delivery.in_transit',
        'out_for_delivery' => 'delivery.out_for_delivery',
        'arrived' => 'delivery.arriving',
        'delivered' => 'delivery.delivered',
        'failed' => 'delivery.failed',
    ];

    /**
     * Planifie la notification du destinataire pour un événement.
     */
    public function scheduleForEvent(DeliveryEvent $event): void
    {
        $template = self::TEMPLATES[$event->type] ?? null;

        if ($template === null) {
            return; // événement sans notification (ex. returned)
        }

        /** @var Delivery|null $delivery */
        $delivery = Delivery::query()->find($event->delivery_id);

        if ($delivery === null || $delivery->dropoff_phone === null || $delivery->dropoff_phone === '') {
            return; // pas de destinataire joignable
        }

        $companyId = (string) ($event->company_id ?? $delivery->company_id);

        // Opt-out : un numéro opt-out arrête les notifications planifiées.
        $optedOut = DeliveryRecipientOptOut::query()
            ->where('company_id', $companyId)
            ->where('phone', $delivery->dropoff_phone)
            ->exists();

        DeliveryNotification::query()->create([
            'company_id' => $companyId,
            'delivery_id' => $event->delivery_id,
            'event_type' => $event->type,
            'channel' => 'whatsapp',
            'recipient_phone' => $delivery->dropoff_phone,
            'template_key' => $template,
            'status' => $optedOut ? 'skipped' : 'pending',
            'payload' => [
                'reference' => $delivery->reference,
                'source' => $delivery->source,
            ],
        ]);
    }

    public function optOut(string $companyId, string $phone): DeliveryRecipientOptOut
    {
        /** @var DeliveryRecipientOptOut $optOut */
        $optOut = DeliveryRecipientOptOut::query()->firstOrCreate([
            'company_id' => $companyId,
            'phone' => $phone,
        ]);

        return $optOut;
    }
}
