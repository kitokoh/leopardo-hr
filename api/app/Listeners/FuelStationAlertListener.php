<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FuelStationAlert;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Facades\Log;

/**
 * Listener global — alerte FuelStation → CommunicationService.
 *
 * Le module FuelStation émet `App\Events\FuelStationAlert` (isolation #5584) ;
 * ce listener (hors modules) traduit l'événement en notifications employés
 * via CommunicationService (préférences + quotas + audit).
 */
class FuelStationAlertListener
{
    public function __construct(private readonly CommunicationService $communication) {}

    public function handle(FuelStationAlert $alert): void
    {
        foreach ($alert->managers as $manager) {
            try {
                $this->communication->notifyEmployee(
                    $manager,
                    $alert->templateKey,
                    ['category' => $alert->category] + $alert->payload,
                    ['app'],
                );
            } catch (\Throwable $e) {
                Log::channel('fuel-station')->warning('fuel.alert.failed', [
                    'template' => $alert->templateKey,
                    'employee_id' => $manager->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
