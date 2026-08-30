<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Enums\FuelIncidentStatus;
use App\Modules\FuelStation\Domain\Enums\FuelMaintenanceTaskStatus;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Support\Facades\DB;

/**
 * #5804 — Workflow audité des incidents et tâches de maintenance (FUEL-010).
 *
 * Machine à états incidents : open → assigned → in_progress → resolved|cancelled
 * (open → in_progress autorisé sans assignation ; resolved/cancelled terminaux).
 * Chaque transition est auditée (AuditLog, action `fuel_incident.*` /
 * `fuel_maintenance_task.*`) — aucune mutation silencieuse.
 */
final class FuelIncidentService
{
    /**
     * Transitions valides : état → états atteignables.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'open' => ['assigned', 'in_progress', 'cancelled', 'resolved'],
        'assigned' => ['in_progress', 'cancelled', 'resolved'],
        'in_progress' => ['resolved', 'cancelled'],
    ];

    public function transition(
        FuelIncident $incident,
        FuelIncidentStatus $to,
        Employee $actor,
        ?string $resolutionNote = null,
    ): FuelIncident {
        $from = $incident->status;

        if ($from === $to->value) {
            return $incident;
        }

        if (! in_array($to->value, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \RuntimeException("Transition incident invalide : {$from} → {$to->value}");
        }

        DB::transaction(function () use ($incident, $to, $actor, $resolutionNote, $from): void {
            $updates = ['status' => $to->value];

            if ($to === FuelIncidentStatus::Resolved) {
                $updates['resolved_by'] = $actor->id;
                $updates['resolved_at'] = now();
                $updates['resolution_note'] = $resolutionNote;
            }

            if ($to === FuelIncidentStatus::Cancelled) {
                $updates['resolution_note'] = $resolutionNote;
            }

            $incident->forceFill($updates)->save();

            AuditLog::create([
                'company_id' => $incident->company_id,
                'user_id' => $actor->id,
                'action' => 'fuel_incident.transition',
                'auditable_type' => FuelIncident::class,
                'auditable_id' => $incident->id,
                'old_values' => ['status' => $from],
                'new_values' => ['status' => $to->value],
                'metadata' => ['from' => $from, 'to' => $to->value],
            ]);
        });

        return $incident->fresh();
    }

    public function assign(FuelIncident $incident, Employee $assignee, Employee $actor): FuelIncident
    {
        if ($assignee->company_id !== $incident->company_id) {
            throw new \RuntimeException('Assignation cross-tenant refusée.');
        }

        $this->guardNotTerminal($incident);

        DB::transaction(function () use ($incident, $assignee, $actor): void {
            $incident->forceFill([
                'assigned_to' => $assignee->id,
                'status' => $incident->status === FuelIncidentStatus::Open->value
                    ? FuelIncidentStatus::Assigned->value
                    : $incident->status,
            ])->save();

            AuditLog::create([
                'company_id' => $incident->company_id,
                'user_id' => $actor->id,
                'action' => 'fuel_incident.assigned',
                'auditable_type' => FuelIncident::class,
                'auditable_id' => $incident->id,
                'old_values' => ['assigned_to' => null],
                'new_values' => ['assigned_to' => $assignee->id],
            ]);
        });

        return $incident->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function completeTask(FuelMaintenanceTask $task, Employee $actor, ?string $note = null, array $context = []): FuelMaintenanceTask
    {
        if ($task->status === FuelMaintenanceTaskStatus::Completed->value) {
            return $task;
        }

        DB::transaction(function () use ($task, $actor, $note): void {
            $task->forceFill([
                'status' => FuelMaintenanceTaskStatus::Completed->value,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'completion_note' => $note,
            ])->save();

            AuditLog::create([
                'company_id' => $task->company_id,
                'user_id' => $actor->id,
                'action' => 'fuel_maint_task.completed',
                'auditable_type' => FuelMaintenanceTask::class,
                'auditable_id' => $task->id,
                'old_values' => ['status' => $task->getOriginal('status')],
                'new_values' => ['status' => FuelMaintenanceTaskStatus::Completed->value],
            ]);
        });

        return $task->fresh();
    }

    private function guardNotTerminal(FuelIncident $incident): void
    {
        if (in_array($incident->status, FuelIncidentStatus::terminal(), true)) {
            throw new \RuntimeException('Incident déjà résolu/annulé — aucune mutation possible.');
        }
    }
}
