<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\DispatchCommunicationJob;
use App\Modules\FuelStation\Domain\Models\FuelAlertLog;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Notifications et alertes FuelStation (FUEL-019, issue #5813).
 *
 * Détecte les anomalies opérationnelles (relevés anormaux, clôtures de
 * caisse manquantes, écarts de stock inexpliqués, maintenance en retard)
 * et notifie les managers du tenant via `DispatchCommunicationJob`
 * (job global tenant-scoped — aucun import croisé inter-module).
 *
 * Déduplication : chaque alerte notifiée est journalisée avec une clé
 * unique par tenant (type + cible + date) — un rejeu du job ne re-notifie
 * jamais la même anomalie. Canaux désactivables via les préférences de
 * notification (catégorie `fuel`).
 */
final class FuelAlertService
{
    /**
     * Détecte et notifie les anomalies du jour. Idempotent (dédup par clé).
     *
     * @return list<string> clés d'alertes notifiées
     */
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

            $managers = $this->managers($companyId);

            foreach ($managers as $manager) {
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

            FuelAlertLog::query()->create([
                'company_id' => $companyId,
                'alert_type' => $type,
                'alert_key' => $key,
                'station_id' => $anomaly['station_id'] ?? null,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'notified_by' => $actor->id,
            ]);

            $notified[] = $key;
        }

        return $notified;
    }

    /**
     * @return list<array{type: string, key: string, station_id: int|null, title: string, body: string, payload: array<string, mixed>}>
     */
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

    /** @return array<int, Employee> */
    private function managers(string $companyId): array
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh'])
            ->limit(10)
            ->get()
            ->all();
    }

    /** @return array<string, mixed> */
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
}
