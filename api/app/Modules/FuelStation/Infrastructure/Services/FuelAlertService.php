<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\FuelStationAlert;
use Illuminate\Support\Facades\Log;
use App\Jobs\DispatchCommunicationJob;use App\Modules\FuelStation\Domain\Models\FuelAlertLog;use App\Modules\FuelStation\Domain\Models\FuelCashSession;use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;use Illuminate\Database\UniqueConstraintViolationException;use Illuminate\Support\Carbon;use Illuminate\Support\Facades\DB;
use App\Core\Notifications\Contracts\InAppNotifier;use App\Modules\FuelStation\Domain\Models\FuelAlert;use App\Modules\FuelStation\Domain\Models\FuelAlertLog;use App\Modules\FuelStation\Domain\Models\FuelCashSession;use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;use App\Modules\Notification\Infrastructure\Services\CommunicationService;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\UniqueConstraintViolationException;use Illuminate\Support\Carbon;use Illuminate\Support\Facades\DB;use Throwable;

/**
 * Notifications & alertes FuelStation — FUEL-019 (issue #5813).
 *
 * Consommateur des événements d'outbox `fuel.*` à destination des employés :
 * - incident rapporté → notification de l'équipe (manager assignable) ;
 * - seuil de stock franchi → alerte du/des manager(s) de la station ;
 * - session de caisse clôturée avec écart → alerte manager.
 *
 * Aucune PII dans les notifications (pas de nom client, pas de description
 * d'incident, montants agrégés). Templates i18n `fuel_*` (fr/en/tr/ar).
 *
 * Isolation des modules (#5584) : FuelStation n'importe PAS le module
 * Notification — il émet l'événement partagé `App\Events\FuelStationAlert`
 * (Events Shared), traduit en notifications par le listener global
 * `App\Listeners\FuelStationAlertListener` (préférences + quotas via
 * CommunicationService). Les échecs sont journalisés et rejoués par
 * l'outbox (retry borné, dead-letter).
 */
final class FuelAlertService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyManagers(Employee $actor, string $templateKey, array $payload, string $category = 'fuel'): void
    {
        $managers = Employee::query()
            ->where('company_id', (string) $actor->company_id)
            ->where('status', 'active')
            ->limit(10)
            ->get()
            ->filter(fn (Employee $employee): bool => $employee->isManager());

        if ($managers->isEmpty()) {
            Log::channel('fuel-station')->info('fuel.alert.no_manager', ['template' => $templateKey]);

            return;
        }

        FuelStationAlert::dispatch($managers, $templateKey, $payload, $category);
    }

    public function dispatchDaily(Employee $actor): array
    {
        $companyId = (string) $actor->company_id;
        $today = Carbon::now('UTC')->toDateString();
        $notified = [];

        foreach ($this->anomalies($companyId, $today) as $anomaly) {
            $type = $anomaly['type'];
            $key = $anomaly['key'];
            $payload = $anomaly['payload'];

            $exists = FuelAlertLog::query()
                ->where('company_id', $companyId)
                ->where('alert_type', $type)
                ->where('alert_key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            // Journalise la dédup AVANT de dispatcher (TOCTOU) : en cas de
            // course entre deux runs, le perdant voit la violation unique et
            // s'arrête — jamais de double notification, jamais de rejeu.
            try {
                FuelAlertLog::query()->create([
                    'company_id' => $companyId,
                    'alert_type' => $type,
                    'alert_key' => $key,
                    'station_id' => $anomaly['station_id'] ?? null,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'notified_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            foreach ($this->managers($companyId) as $manager) {
                DispatchCommunicationJob::dispatch(
                    employeeId: (int) $manager->id,
                    companyId: $companyId,
                    templateKey: $this->templateFor($type),
                    context: [
                        'title' => $anomaly['title'],
                        'body' => $anomaly['body'],
                        'category' => 'fuel',
                    ],
                    channels: ['app', 'push'],
                );
            }

            $notified[] = $key;
        }

        return $notified;
    }

    private function anomalies(string $companyId, string $today): array
    {
        $anomalies = [];

        // 1. Relevés anormaux (intervalles en statut anomaly — la revue passe
        //    le statut à valid/rollover, voir MeterReadingService::review).
        $intervals = FuelMeterInterval::query()
            ->where('company_id', $companyId)
            ->where('calculation_status', FuelMeterInterval::STATUS_ANOMALY)
            ->limit(20)
            ->get();

        foreach ($intervals as $interval) {
            $stationId = $interval->getAttribute('station_id');
            $anomalies[] = [
                'type' => FuelAlertLog::TYPE_METER_ANOMALY,
                'key' => 'meter-anomaly:'.$interval->id,
                'station_id' => is_numeric($stationId) ? (int) $stationId : null,
                'title' => 'Anomalie de relevé détectée',
                'body' => 'Intervalle de relevé anormal non revu (intervalle #'.$interval->id.').',
                'payload' => ['interval_id' => $interval->id],
            ];
        }

        // 2. Clôtures de caisse manquantes (sessions ouvertes > 24 h).
        $openSessions = FuelCashSession::query()
            ->where('company_id', $companyId)
            ->where('status', FuelCashSession::STATUS_OPEN)
            ->where('opened_at', '<', Carbon::now('UTC')->subHours(24))
            ->limit(20)
            ->get();

        foreach ($openSessions as $session) {
            $anomalies[] = [
                'type' => FuelAlertLog::TYPE_MISSING_CLOSURE,
                'key' => 'missing-closure:'.$session->id.':'.$today,
                'station_id' => $session->station_id,
                'title' => 'Clôture de caisse manquante',
                'body' => 'Session de caisse #'.$session->id.' ouverte depuis plus de 24 h sans clôture.',
                'payload' => ['session_id' => $session->id],
            ];
        }

        // 3. Écarts de stock inexpliqués (dernier rapprochement).
        $runs = FuelReconciliationRun::query()
            ->where('company_id', $companyId)
            ->where('status', FuelReconciliationRun::STATUS_COMPLETED)
            ->where('run_date', $today)
            ->get();

        foreach ($runs as $run) {
            $summary = is_array($run->summary) ? $run->summary : [];
            if (($summary['explainable'] ?? false) === true) {
                continue;
            }

            $anomalies[] = [
                'type' => FuelAlertLog::TYPE_STOCK_VARIANCE,
                'key' => 'stock-variance:'.$run->id,
                'station_id' => $run->station_id,
                'title' => 'Écart de stock inexpliqué',
                'body' => 'Rapprochement #'.$run->id.' : écart non expliqué (total '.(is_numeric($summary['total_variance_minor'] ?? null) ? (string) $summary['total_variance_minor'] : '0').' unités).',
                'payload' => ['reconciliation_run_id' => $run->id],
            ];
        }

        // 4. Tâches de maintenance en retard.
        $tasks = FuelMaintenanceTask::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [FuelMaintenanceTask::STATUS_OPEN, FuelMaintenanceTask::STATUS_IN_PROGRESS])
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now('UTC'))
            ->limit(20)
            ->get();

        foreach ($tasks as $task) {
            $anomalies[] = [
                'type' => FuelAlertLog::TYPE_MAINTENANCE_DUE,
                'key' => 'maintenance-due:'.$task->id,
                'station_id' => $task->station_id,
                'title' => 'Maintenance en retard',
                'body' => 'Tâche « '.$task->title.' » dépassée (échéance '.$task->due_at?->toDateString().').',
                'payload' => ['task_id' => $task->id],
            ];
        }

        return $anomalies;
    }


    private function templateFor(string $type): string
    {
        return match ($type) {
            FuelAlertLog::TYPE_METER_ANOMALY => 'fuel_meter_anomaly',
            FuelAlertLog::TYPE_MISSING_CLOSURE => 'fuel_missing_closure',
            FuelAlertLog::TYPE_STOCK_VARIANCE => 'fuel_stock_variance',
            FuelAlertLog::TYPE_MAINTENANCE_DUE => 'fuel_maintenance_due',
            default => 'generic',
        };
    }

    private function managers(string $companyId): array
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh'])
            ->where('status', 'active')
            ->limit(10)
            ->get()
            ->all();
    }

    public function stats(string $companyId, string $since): array
    {
        $counts = DB::table('fuel_alert_log')
            ->where('company_id', $companyId)
            ->where('notified_at', '>=', $since)
            ->selectRaw('alert_type, COUNT(*) as total')
            ->groupBy('alert_type')
            ->pluck('total', 'alert_type');

        return [
            'since' => $since,
            'total' => (int) array_sum($counts->all()),
            'by_type' => $counts->all(),
        ];
    }

    public function __construct(private readonly CommunicationService $communication) {}

    public function __construct(
        private readonly InAppNotifier $notifier,
    ) {}

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

        try {
            $notified = $this->notifyManagers($companyId, $stationId, $eventType, $severity, $payload);
        } catch (Throwable) {
            // Best-effort : une notification en échec ne casse jamais le flux
            // métier qui a créé l'alerte.
            $notified = 0;
        }

        return ['alert' => $alert, 'created' => true, 'notified' => $notified];
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
            // ASC = NULL (global) en dernier : la préférence station-spécifique
            // prime sur la préférence globale (PG : NULLS LAST en ASC).
            ->orderBy('station_id')
            ->first();

        // Absence de ligne = in_app activé par défaut ; les autres canaux
        // (email/push) sont inactifs tant qu'aucune préférence n'existe.
        if (! $preference instanceof FuelNotificationPreference) {
            return $channel === FuelNotificationPreference::CHANNEL_IN_APP;
        }

        return (bool) $preference->enabled;
    }

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