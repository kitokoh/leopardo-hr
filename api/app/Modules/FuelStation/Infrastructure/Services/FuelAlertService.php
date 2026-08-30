<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use App\Core\Notifications\Contracts\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Alertes & notifications FuelStation — FUEL-019 (#5813).
 *
 * - Déduplication : `alert_key` unique par tenant — un re-scan ou un rejeu
 *   d'événement ne crée jamais deux alertes identiques ;
 * - Préférences tenant : canal désactivable par type d'événement et par
 *   station (absence de ligne = in_app activé) ;
 * - Pas de PII/secrets dans les payloads ni les notifications ;
 * - Notification in-app best-effort (échec isolé, jamais bloquant).
 */
final class FuelAlertService
{
    public function __construct(
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * Crée une alerte dédupliquée. Retourne l'alerte (existante ou
     * nouvelle) et notifie les managers du tenant si le canal in_app est
     * activé pour ce type d'événement.
     *
     * @param  array<string, mixed>  $payload
     *
     * @return array{alert: FuelAlert, created: bool, notified: int}
     */
    public function createAlert(
        string $companyId,
        ?int $stationId,
        string $eventType,
        string $severity,
        string $alertKey,
        array $payload,
    ): array {
        /** @var FuelAlert|null $existing */
        $existing = FuelAlert::query()
            ->where('company_id', $companyId)
            ->where('alert_key', $alertKey)
            ->first();

        if ($existing instanceof FuelAlert) {
            return ['alert' => $existing, 'created' => false, 'notified' => 0];
        }

        try {
            /** @var FuelAlert $alert */
            $alert = FuelAlert::query()->create([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'event_type' => $eventType,
                'severity' => $severity,
                'alert_key' => $alertKey,
                'payload' => $payload,
                'status' => FuelAlert::STATUS_OPEN,
            ]);
        } catch (Throwable) {
            // Course entre deux workers : la contrainte unique a arbitré.
            /** @var FuelAlert $alert */
            $alert = FuelAlert::query()
                ->where('company_id', $companyId)
                ->where('alert_key', $alertKey)
                ->firstOrFail();

            return ['alert' => $alert, 'created' => false, 'notified' => 0];
        }

        $notified = $this->notifyManagers($companyId, $stationId, $eventType, $severity, $payload);

        return ['alert' => $alert, 'created' => true, 'notified' => $notified];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyManagers(
        string $companyId,
        ?int $stationId,
        string $eventType,
        string $severity,
        array $payload,
    ): int {
        if (! $this->channelEnabled($companyId, $stationId, $eventType, FuelNotificationPreference::CHANNEL_IN_APP)) {
            return 0;
        }

        $managers = Employee::query()
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->where('status', 'active')
            ->pluck('id');

        $notified = 0;

        foreach ($managers as $managerId) {
            try {
                $this->notifier->dispatch(
                    userId: (int) $managerId,
                    type: "fuel.alert.{$eventType}",
                    title: $this->titleFor($eventType, $severity),
                    body: $this->bodyFor($eventType, $payload),
                    data: ['event_type' => $eventType, 'severity' => $severity, 'payload' => $payload],
                );
                $notified++;
            } catch (Throwable) {
                // Best-effort : une notification en échec ne casse pas l'alerte.
            }
        }

        return $notified;
    }

    public function channelEnabled(string $companyId, ?int $stationId, string $eventType, string $channel): bool
    {
        $preference = FuelNotificationPreference::query()
            ->where('company_id', $companyId)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->where(function (Builder $query) use ($stationId): void {
                $query->where('station_id', $stationId)->orWhereNull('station_id');
            })
            ->orderByDesc('station_id')
            ->first();

        // Absence de ligne = in_app activé par défaut ; les autres canaux
        // (email/push) sont inactifs tant qu'aucune préférence n'existe.
        if (! $preference instanceof FuelNotificationPreference) {
            return $channel === FuelNotificationPreference::CHANNEL_IN_APP;
        }

        return (bool) $preference->enabled;
    }

    /**
     * Upsert des préférences (bulk, transactionnel).
     *
     * @param  list<array{event_type: string, channel: string, enabled: bool, station_id?: int|null}>  $preferences
     */
    public function upsertPreferences(string $companyId, array $preferences): int
    {
        $count = 0;

        DB::transaction(function () use ($companyId, $preferences, &$count): void {
            foreach ($preferences as $preference) {
                $stationId = isset($preference['station_id']) && is_int($preference['station_id'])
                    ? $preference['station_id']
                    : null;

                FuelNotificationPreference::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'station_id' => $stationId,
                        'event_type' => $preference['event_type'],
                        'channel' => $preference['channel'],
                    ],
                    ['enabled' => (bool) $preference['enabled']],
                );

                $count++;
            }
        });

        return $count;
    }

    private function titleFor(string $eventType, string $severity): string
    {
        $titles = [
            FuelNotificationPreference::EVENT_READING_ANOMALY => 'Anomalie de relevé de compteur',
            FuelNotificationPreference::EVENT_STOCK_VARIANCE => 'Écart de stock détecté',
            FuelNotificationPreference::EVENT_MISSING_CLOSE => 'Clôture de caisse manquante',
            FuelNotificationPreference::EVENT_MAINTENANCE_DUE => 'Maintenance due',
            FuelNotificationPreference::EVENT_INCIDENT => 'Incident signalé',
        ];

        $base = $titles[$eventType] ?? 'Alerte FuelStation';

        return $severity === FuelAlert::SEVERITY_CRITICAL ? "[CRITIQUE] {$base}" : $base;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bodyFor(string $eventType, array $payload): string
    {
        return match ($eventType) {
            FuelNotificationPreference::EVENT_STOCK_VARIANCE => sprintf(
                'Écart de %d (tolérance %d) sur %s (%s → %s).',
                (int) ($payload['variance_minor'] ?? 0),
                (int) ($payload['tolerance_minor'] ?? 0),
                (string) ($payload['product_type'] ?? '?'),
                (string) ($payload['period_start'] ?? '?'),
                (string) ($payload['period_end'] ?? '?'),
            ),
            FuelNotificationPreference::EVENT_MISSING_CLOSE => sprintf(
                'Session de caisse #%d ouverte le %s toujours pas clôturée.',
                (int) ($payload['session_id'] ?? 0),
                (string) ($payload['opened_at'] ?? '?'),
            ),
            FuelNotificationPreference::EVENT_MAINTENANCE_DUE => sprintf(
                'Tâche « %s » (%s) à échéance %s.',
                (string) ($payload['title'] ?? '?'),
                (string) ($payload['priority'] ?? '?'),
                (string) ($payload['due_at'] ?? '?'),
            ),
            FuelNotificationPreference::EVENT_INCIDENT => sprintf(
                'Incident #%d : %s (sévérité %s).',
                (int) ($payload['incident_id'] ?? 0),
                (string) ($payload['title'] ?? '?'),
                (string) ($payload['severity'] ?? '?'),
            ),
            default => 'Alerte FuelStation — voir le détail dans l\'application.',
        };
    }
}
