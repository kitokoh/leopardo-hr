<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Exceptions\FuelIncidentTransitionException;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Carbon;

/**
 * Cycle de vie des incidents FuelStation (FUEL-010, issue #5804).
 *
 * Transitions validées en application (jamais en base) :
 *   open → assigned → in_progress → resolved → closed
 * Chaque transition est tracée dans `audit_logs` (action `fuel.incident.*`)
 * avec l'acteur et les valeurs avant/après — workflow audité, aucune donnée
 * PII dans les payloads.
 */
final class FuelIncidentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $actor, FuelStation $station, array $data): FuelIncident
    {
        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'status' => FuelIncident::STATUS_OPEN,
            'reported_by' => $actor->id,
            'occurred_at' => isset($data['occurred_at'])
                ? Carbon::parse((string) $data['occurred_at'])
                : Carbon::now(),
        ]));

        $this->audit($actor, $incident, 'fuel.incident.created', [], [
            'station_id' => $station->id,
            'severity' => $incident->severity,
            'equipment_type' => $incident->equipment_type,
        ]);

        return $incident;
    }

    public function assign(Employee $actor, FuelIncident $incident, ?int $employeeId): FuelIncident
    {
        $this->assertTransition($incident, [FuelIncident::STATUS_OPEN]);

        $incident->update([
            'status' => FuelIncident::STATUS_ASSIGNED,
            'assigned_to' => $employeeId,
        ]);

        $this->audit($actor, $incident->refresh(), 'fuel.incident.assigned', [], [
            'assigned_to' => $employeeId,
        ]);

        return $incident;
    }

    public function start(Employee $actor, FuelIncident $incident): FuelIncident
    {
        $this->assertTransition($incident, [FuelIncident::STATUS_OPEN, FuelIncident::STATUS_ASSIGNED]);

        $incident->update(['status' => FuelIncident::STATUS_IN_PROGRESS]);

        $this->audit($actor, $incident->refresh(), 'fuel.incident.started', [], []);

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(Employee $actor, FuelIncident $incident, array $data): FuelIncident
    {
        $this->assertTransition($incident, [
            FuelIncident::STATUS_OPEN,
            FuelIncident::STATUS_ASSIGNED,
            FuelIncident::STATUS_IN_PROGRESS,
        ]);

        $incident->update([
            'status' => FuelIncident::STATUS_RESOLVED,
            'resolved_by' => $actor->id,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_at' => Carbon::now(),
        ]);

        $this->audit($actor, $incident->refresh(), 'fuel.incident.resolved', [], [
            'resolution_notes' => $data['resolution_notes'] ?? null,
        ]);

        return $incident;
    }

    public function close(Employee $actor, FuelIncident $incident): FuelIncident
    {
        $this->assertTransition($incident, [FuelIncident::STATUS_RESOLVED]);

        $incident->update([
            'status' => FuelIncident::STATUS_CLOSED,
            'closed_at' => Carbon::now(),
        ]);

        $this->audit($actor, $incident->refresh(), 'fuel.incident.closed', [], []);

        return $incident;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertTransition(FuelIncident $incident, array $allowed): void
    {
        if (! in_array($incident->status, $allowed, true)) {
            throw new FuelIncidentTransitionException(
                sprintf('Transition %s → non autorisée.', (string) $incident->status)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function audit(Employee $actor, FuelIncident $incident, string $action, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => FuelIncident::class,
            'auditable_id' => $incident->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
