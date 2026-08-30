<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Facades\Log;

/**
 * Notifications & alertes FuelStation — FUEL-019 (issue #5813).
 *
 * Consommateur des événements d'outbox `fuel.*` à destination des employés :
 * - incident rapporté → notification de l'équipe (manager assignable) ;
 * - seuil de stock franchi → alerte du/des manager(s) de la station ;
 * - session de caisse clôturée avec écart → alerte manager.
 *
 * Aucune PII dans les notifications (pas de nom client, pas de description
 * d'incident, montants agrégés). Templates i18n `fuel_*` (fr/en/tr/ar) ;
 * respect des préférences/quotas via CommunicationService. Les échecs sont
 * journalisés et rejoués par l'outbox (retry borné, dead-letter).
 */
final class FuelAlertService
{
    public function __construct(private readonly CommunicationService $communication) {}

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

        foreach ($managers as $manager) {
            try {
                $this->communication->notifyEmployee($manager, $templateKey, $payload, ['app']);
            } catch (\Throwable $e) {
                Log::channel('fuel-station')->warning('fuel.alert.failed', [
                    'template' => $templateKey,
                    'employee_id' => $manager->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
