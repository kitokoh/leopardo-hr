<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Événement partagé — alerte FuelStation à destination des employés.
 *
 * Pattern Events Shared (isolation des modules #5584) : le module
 * FuelStation n'importe pas Notification ; il émet cet événement et le
 * listener global (App\Listeners) délègue à CommunicationService.
 *
 * Aucune PII : pas de nom client, pas de description d'incident, montants
 * agrégés (voir FuelAlertService — FUEL-019, #5813).
 */
class FuelStationAlert
{
    use Dispatchable;

    /**
     * @param  Collection<int, Employee>  $managers
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Collection $managers,
        public readonly string $templateKey,
        public readonly array $payload,
        public readonly string $category = 'fuel',
    ) {}
}
