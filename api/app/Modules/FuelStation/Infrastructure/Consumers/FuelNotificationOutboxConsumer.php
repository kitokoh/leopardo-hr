<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Consumers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;

/**
 * Consommateur des événements FuelStation à destination des employés
 * (FUEL-019, issue #5813).
 *
 * - fuel.incident.reported.v1 → notification de l'équipe (SANS PII : pas de
 *   description, pas de nom) ;
 * - fuel.stock.threshold.breached.v1 → alerte managers de la station.
 *
 * Idempotent par construction (un événement d'outbox est traité une seule
 * fois — statut sent). Les préférences/quotas sont gérés par
 * CommunicationService.
 */
final class FuelNotificationOutboxConsumer implements FuelOutboxConsumer
{
    /**
     * @return list<string>
     */
    private const SUPPORTED = [
        FuelOutboxEvent::EVENT_INCIDENT_REPORTED,
        FuelOutboxEvent::EVENT_STOCK_THRESHOLD_BREACHED,
    ];

    public function __construct(private readonly FuelAlertService $alerts) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $eventType = is_string($payload['_event_type'] ?? null) ? $payload['_event_type'] : null;

        if ($eventType === FuelOutboxEvent::EVENT_INCIDENT_REPORTED) {
            $this->notifyIncident($payload);

            return;
        }

        if ($eventType === FuelOutboxEvent::EVENT_STOCK_THRESHOLD_BREACHED) {
            $this->notifyLowStock($payload);

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyIncident(array $payload): void
    {
        $incident = FuelIncident::query()->find((int) ($payload['incident_id'] ?? 0));

        if (! $incident instanceof FuelIncident || ! $incident->reported_by) {
            return;
        }

        /** @var Employee|null $reporter */
        $reporter = Employee::query()->find($incident->reported_by);

        if (! $reporter instanceof Employee) {
            return;
        }

        // SANS PII : pas de titre/description d'incident dans la notification.
        $this->alerts->notifyManagers($reporter, 'fuel_incident_reported', [
            'category' => 'fuel',
            'severity' => $incident->severity,
            'station_id' => (string) $incident->station_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyLowStock(array $payload): void
    {
        $stationId = (int) ($payload['station_id'] ?? 0);
        $product = is_string($payload['product_code'] ?? null) ? $payload['product_code'] : '';
        $level = (float) ($payload['level_litres'] ?? 0);

        // On alerte via le premier employé actif du tenant (porteur du
        // contexte) ; le template ne porte que des valeurs agrégées.
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('status', 'active')
            ->first();

        if (! $actor instanceof Employee) {
            return;
        }

        $this->alerts->notifyManagers($actor, 'fuel_stock_low', [
            'category' => 'fuel',
            'station_id' => (string) $stationId,
            'product' => $product,
            'level' => (string) $level,
        ]);
    }
}
