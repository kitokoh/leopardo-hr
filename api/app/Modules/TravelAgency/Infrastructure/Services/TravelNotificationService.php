<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\TravelAgency\Domain\Contracts\TravelCustomerContactResolver;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;

/**
 * TRAVEL-415 (#6067) — Notifications voyageur via les canaux BC-13.
 *
 * Consommation des événements outbox travel (confirmation/annulation/
 * paiement/billet) : une notification n'est émise QUE si
 *  - le consentement du client est explicite (`travel_bookings.notify_consent`,
 *    défaut FALSE — « pas d'envoi par défaut ») ;
 *  - le canal est configuré au niveau plateforme
 *    (`config('communication.default_channels')`, mail/sms/whatsapp).
 *
 * Contenu : résumé minimal + référence (jamais de données financières dans
 * WhatsApp). L'envoi effectif est délégué à la file BC-13 via
 * `CommunicationEvent` (status pending, event_name travel.*, template_key,
 * metadata redigée) — le contrat d'envoi reste dans le BC COMMS.
 */
final class TravelNotificationService
{
    public const SUPPORTED_EVENTS = [
        'travel.booking.confirmed.v1',
        'travel.booking.cancelled.v1',
        'travel.payment.confirmed.v1',
        'travel.payment.refunded.v1',
        'travel.ticket.issued.v1',
    ];

    public function __construct(private readonly TravelCustomerContactResolver $contacts)
    {
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function notify(string $companyId, string $eventType, array $payload): int
    {
        $bookingReference = isset($payload['booking_reference']) && is_string($payload['booking_reference'])
            ? $payload['booking_reference']
            : null;

        if ($bookingReference === null) {
            return 0;
        }

        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()
            ->where('company_id', $companyId)
            ->where('reference', $bookingReference)
            ->first();

        if (! $booking instanceof TravelBooking) {
            return 0;
        }

        // Consentement explicite requis (pas d'envoi par défaut).
        if (! $booking->notify_consent) {
            return 0;
        }

        // Résolution éphémère du contact client (contrat CRM, jamais d'écriture).
        $contact = $booking->customer_contact_id !== null
            ? $this->contacts->resolve($companyId, (string) $booking->customer_contact_id)
            : null;

        $channels = $this->configuredChannels();

        if ($channels === []) {
            return 0;
        }

        $summary = $this->summary($eventType, $booking, $payload);
        $created = 0;

        foreach ($channels as $channel) {
            CommunicationEvent::query()->create([
                'company_id' => $companyId,
                'employee_id' => null,
                'event_name' => $eventType,
                'channel' => $channel,
                'status' => 'pending',
                'template_key' => 'travel.'.$this->templateKey($eventType),
                'metadata' => [
                    'booking_reference' => $booking->reference,
                    'contact_reference' => $booking->customer_contact_id,
                    'contact_email' => $contact['email'] ?? null,
                    'contact_phone' => $contact['phone'] ?? null,
                    'summary' => $summary,
                ],
                'occurred_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * @return list<string> canaux configurés au niveau plateforme (BC-13)
     */
    private function configuredChannels(): array
    {
        $defaults = config('communication.default_channels', ['email']);

        if (! is_array($defaults)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $defaults),
            fn (string $channel): bool => in_array($channel, ['email', 'sms', 'whatsapp'], true)
        ));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function summary(string $eventType, TravelBooking $booking, array $payload): string
    {
        return match ($eventType) {
            'travel.booking.confirmed.v1' => 'Réservation confirmée '.$booking->reference,
            'travel.booking.cancelled.v1' => 'Réservation annulée '.$booking->reference,
            'travel.payment.confirmed.v1' => 'Paiement confirmé '.$booking->reference,
            'travel.payment.refunded.v1' => 'Remboursement '.$booking->reference,
            'travel.ticket.issued.v1' => 'Billet émis '.$booking->reference,
            default => 'Mise à jour '.$booking->reference,
        };
    }

    private function templateKey(string $eventType): string
    {
        return str_replace('.', '_', $eventType);
    }
}
