<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;
use Illuminate\Console\Command;

/**
 * fuel:alerts-scan — Détection périodique des anomalies FuelStation
 * (FUEL-019, #5813).
 *
 * Pour chaque tenant avec la solution active :
 * - anomalies de relevés (intervalles `anomaly` non alertés) ;
 * - sessions de caisse ouvertes depuis plus de `--max-hours` (défaut 24) ;
 * - tâches de maintenance dues dans les 48 h ou en retard.
 *
 * Déduplication par `alert_key` : un re-scan ne crée jamais de doublon.
 *
 * Usage : php artisan fuel:alerts-scan [--max-hours=24]
 * Scheduler : toutes les 30 minutes.
 */
class FuelAlertsScanCommand extends Command
{
    protected $signature = 'fuel:alerts-scan
        {--max-hours=24 : âge maximal d\'une session de caisse ouverte (défaut 24)}';

    protected $description = 'Détecte et crée les alertes FuelStation (anomalies, clôtures manquantes, maintenance due) — dédupliquées.';

    public function __construct(
        private readonly FuelAlertService $alerts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $maxHours = max(1, (int) $this->option('max-hours'));
        $created = 0;

        $companies = Company::query()->where('features->fuel_station', true)->get();

        foreach ($companies as $company) {
            $created += $this->scanCompany($company->id, $maxHours);
        }

        $this->info("[fuel:alerts-scan] {$created} alerte(s) créée(s).");

        return self::SUCCESS;
    }

    private function scanCompany(string $companyId, int $maxHours): int
    {
        $created = 0;

        // 1. Anomalies de relevés (intervalles en anomalie).
        $anomalies = FuelMeterInterval::query()
            ->where('company_id', $companyId)
            ->where('calculation_status', FuelMeterInterval::STATUS_ANOMALY)
            ->limit(200)
            ->get();

        foreach ($anomalies as $interval) {
            $result = $this->alerts->createAlert(
                companyId: $companyId,
                stationId: null,
                eventType: FuelNotificationPreference::EVENT_READING_ANOMALY,
                severity: FuelAlert::SEVERITY_HIGH,
                alertKey: "reading_anomaly:{$interval->id}",
                payload: [
                    'interval_id' => $interval->id,
                    'meter_id' => $interval->meter_id,
                    'delta_minor' => $interval->delta_minor,
                    'calculated_at' => $interval->calculated_at?->toISOString(),
                ],
            );

            $created += $result['created'] ? 1 : 0;
        }

        // 2. Sessions de caisse ouvertes trop longtemps.
        $cutoff = now()->subHours($maxHours);

        $openSessions = FuelCashSession::query()
            ->where('company_id', $companyId)
            ->where('status', FuelCashSession::STATUS_OPEN)
            ->where('opened_at', '<=', $cutoff)
            ->limit(200)
            ->get();

        foreach ($openSessions as $session) {
            $result = $this->alerts->createAlert(
                companyId: $companyId,
                stationId: $session->station_id,
                eventType: FuelNotificationPreference::EVENT_MISSING_CLOSE,
                severity: FuelAlert::SEVERITY_WARNING,
                alertKey: "missing_close:{$session->id}",
                payload: [
                    'session_id' => $session->id,
                    'station_id' => $session->station_id,
                    'opened_at' => $session->opened_at?->toISOString(),
                ],
            );

            $created += $result['created'] ? 1 : 0;
        }

        // 3. Tâches de maintenance dues (48 h) ou en retard.
        $horizon = now()->addHours(48);

        $tasks = FuelMaintenanceTask::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [FuelMaintenanceTask::STATUS_PENDING, FuelMaintenanceTask::STATUS_IN_PROGRESS])
            ->where('due_at', '<=', $horizon)
            ->limit(200)
            ->get();

        foreach ($tasks as $task) {
            $result = $this->alerts->createAlert(
                companyId: $companyId,
                stationId: $task->station_id,
                eventType: FuelNotificationPreference::EVENT_MAINTENANCE_DUE,
                severity: $task->priority === FuelMaintenanceTask::PRIORITY_HIGH
                    ? FuelAlert::SEVERITY_HIGH
                    : FuelAlert::SEVERITY_INFO,
                alertKey: "maintenance_due:{$task->id}",
                payload: [
                    'task_id' => $task->id,
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'due_at' => $task->due_at?->toISOString(),
                ],
            );

            $created += $result['created'] ? 1 : 0;
        }

        return $created;
    }
}
