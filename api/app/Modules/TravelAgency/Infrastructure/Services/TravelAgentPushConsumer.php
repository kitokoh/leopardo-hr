<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * TRAVEL-703 (#6090) — Notifications push agents (FCM).
 *
 * À la création/confirmation d'une réservation, prévient les agents du
 * tenant (rôles manage : principal/rh/manager) par push mobile (FCM, via le
 * service de notifications BC-13). Best-effort : un échec de push ne casse
 * jamais le traitement de l'événement (l'outbox marque published).
 */
final class TravelAgentPushConsumer implements TravelOutboxConsumer
{
    private const EVENTS = [
        'travel.booking.pending.v1',
        'travel.booking.confirmed.v1',
    ];

    public function __construct(private readonly PushNotificationService $push) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::EVENTS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $companyId = isset($payload['company_id']) ? (string) $payload['company_id'] : '';
        $eventType = isset($payload['event_type']) ? (string) $payload['event_type'] : '';

        if ($companyId === '') {
            return;
        }

        $reference = isset($payload['booking_reference']) ? (string) $payload['booking_reference'] : '';

        $title = $eventType === 'travel.booking.confirmed.v1'
            ? 'Réservation confirmée'
            : 'Nouvelle réservation';

        $body = $reference !== ''
            ? "Réservation {$reference} — à traiter sur l'app agent."
            : 'Nouvelle réservation — à traiter sur l\'app agent.';

        // Agents du tenant (rôles manage) — push best-effort.
        /** @var list<Employee> $agents */
        $agents = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('manager_role')
            ->whereIn('manager_role', ['principal', 'rh', 'manager'])
            ->limit(50)
            ->get()
            ->all();

        foreach ($agents as $agent) {
            $this->push->sendToUser((int) $agent->id, $title, $body, [
                'module' => 'travel',
                'booking_reference' => $reference,
            ]);
        }
    }
}
