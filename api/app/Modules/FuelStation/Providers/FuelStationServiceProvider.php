<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\FuelStation\Domain\Events\FuelIncidentReported;
use App\Modules\FuelStation\Domain\Events\FuelStockVarianceDetected;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use App\Modules\FuelStation\Domain\Solution\FuelStationManifest;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Module FuelStation — enregistre le manifest de solution dans le
 * catalogue (allowlist) et les écouteurs d'alertes (FUEL-019, #5813).
 */
class FuelStationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('fuel_station', static fn (): FuelStationManifest => new FuelStationManifest);
        });
    }

    public function boot(): void
    {
        // FUEL-019 (#5813) : alertes émises à partir des événements de
        // domaine — dédupliquées par alert_key, canaux pilotés par les
        // préférences tenant, jamais de PII dans les payloads.
        Event::listen(function (FuelStockVarianceDetected $event): void {
            $reconciliation = $event->reconciliation;

            /** @var FuelAlertService $alerts */
            $alerts = resolve(FuelAlertService::class);
            $alerts->createAlert(
                companyId: $reconciliation->company_id,
                stationId: $reconciliation->station_id,
                eventType: FuelNotificationPreference::EVENT_STOCK_VARIANCE,
                severity: FuelAlert::SEVERITY_HIGH,
                alertKey: "stock_variance:{$reconciliation->id}",
                payload: [
                    'reconciliation_id' => $reconciliation->id,
                    'product_type' => $reconciliation->product_type,
                    'period_start' => $reconciliation->period_start?->toDateString(),
                    'period_end' => $reconciliation->period_end?->toDateString(),
                    'variance_minor' => $reconciliation->variance_minor,
                    'tolerance_minor' => $reconciliation->variance_tolerance_minor,
                ],
            );
        });

        Event::listen(function (FuelIncidentReported $event): void {
            $incident = $event->incident;

            $critical = in_array($incident->severity, ['high', 'critical'], true);

            /** @var FuelAlertService $alerts */
            $alerts = resolve(FuelAlertService::class);
            $alerts->createAlert(
                companyId: $incident->company_id,
                stationId: $incident->station_id,
                eventType: FuelNotificationPreference::EVENT_INCIDENT,
                severity: $incident->severity === 'critical'
                    ? FuelAlert::SEVERITY_CRITICAL
                    : ($critical ? FuelAlert::SEVERITY_HIGH : FuelAlert::SEVERITY_INFO),
                alertKey: "incident:{$incident->id}",
                payload: [
                    'incident_id' => $incident->id,
                    'title' => $incident->title,
                    'severity' => $incident->severity,
                    'equipment_type' => $incident->equipment_type,
                ],
            );
        });
    }
}
