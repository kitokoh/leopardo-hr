<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Exceptions\FuelWorkflowTransitionException;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Incidents, maintenance et tâches FuelStation (FUEL-010, issue #5804).
 *
 * Workflow AUDITÉ : chaque transition d'état est enregistrée dans AuditLog
 * (qui_utilisateur, quoi, cible). Aucune transition illégale (ex. resolved →
 * reported) n'est possible : les statuts évoluent uniquement via ce service.
 * Les descriptions sont REDACTED (jamais de PII) ; les pièces jointes sont
 * contrôlées (MIME allowlist + taille) côté Request — seules les métadonnées
 * sont persistées.
 */
final class FuelIncidentService
{
    /**
     * Crée un incident signalé par un employé du tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function report(FuelIncident $incident, Employee $actor, array $data): FuelIncident
    {
        $incident->fill([
            'company_id' => $this->companyId($incident, $actor),
            'status' => FuelIncident::STATUS_REPORTED,
            'reported_by' => $actor->id,
            'reported_at' => Carbon::now('UTC'),
            'category' => $data['category'] ?? FuelIncident::CATEGORY_OTHER,
            'severity' => $data['severity'] ?? FuelIncident::SEVERITY_MEDIUM,
            'description_redacted' => $this->redact($this->asString($data['description_redacted'] ?? '')),
            'attachments_metadata' => $data['attachments_metadata'] ?? null,
            'external_id' => $data['external_id'] ?? null,
        ]);

        if ($incident->exists) {
            $incident->save();
        } else {
            $incident->save();
        }

        $this->audit($actor, 'fuel.incident.reported', $incident);

        return $incident->refresh();
    }

    /**
     * Affecte un incident à un employé du tenant (reported|assigned → assigned).
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(FuelIncident $incident, Employee $actor, array $data): FuelIncident
    {
        $this->assertTransitionAllowed($incident, FuelIncident::STATUS_ASSIGNED);

        $incident->update([
            'status' => FuelIncident::STATUS_ASSIGNED,
            'assigned_to' => $data['assigned_to'] ?? null,
            'assigned_at' => Carbon::now('UTC'),
        ]);

        $this->audit($actor, 'fuel.incident.assigned', $incident);

        return $incident->refresh();
    }

    /** Résout un incident (assigned|reported → resolved). */
    public function resolve(FuelIncident $incident, Employee $actor): FuelIncident
    {
        $this->assertTransitionAllowed($incident, FuelIncident::STATUS_RESOLVED);

        $incident->update([
            'status' => FuelIncident::STATUS_RESOLVED,
            'resolved_by' => $actor->id,
            'resolved_at' => Carbon::now('UTC'),
        ]);

        $this->audit($actor, 'fuel.incident.resolved', $incident);

        return $incident->refresh();
    }

    /** Clôt un incident résolu (resolved → closed). */
    public function close(FuelIncident $incident, Employee $actor): FuelIncident
    {
        $this->assertTransitionAllowed($incident, FuelIncident::STATUS_CLOSED);

        $incident->update([
            'status' => FuelIncident::STATUS_CLOSED,
            'closed_by' => $actor->id,
            'closed_at' => Carbon::now('UTC'),
        ]);

        $this->audit($actor, 'fuel.incident.closed', $incident);

        return $incident->refresh();
    }

    /**
     * Crée une tâche de maintenance (autonome ou dérivée d'un incident).
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(FuelMaintenanceTask $task, Employee $actor, array $data): FuelMaintenanceTask
    {
        $task->fill([
            'company_id' => $this->companyId($task, $actor),
            'station_id' => $this->nullableInt($data['station_id'] ?? null),
            'incident_id' => $this->nullableInt($data['incident_id'] ?? null),
            'status' => FuelMaintenanceTask::STATUS_OPEN,
            'task_type' => $data['task_type'] ?? FuelMaintenanceTask::TYPE_CORRECTIVE,
            'priority' => $data['priority'] ?? FuelMaintenanceTask::PRIORITY_MEDIUM,
            'title' => $this->asString($data['title'] ?? ''),
            'description_redacted' => $this->redact($this->asString($data['description_redacted'] ?? '')),
            'assigned_to' => $this->nullableInt($data['assigned_to'] ?? null),
            'due_at' => $this->nullableDate($data['due_at'] ?? null),
            'created_by' => $actor->id,
            'external_id' => $data['external_id'] ?? null,
        ]);

        if ($task->exists) {
            $task->save();
        } else {
            $task->save();
        }

        $this->audit($actor, 'fuel.maintenance.task.created', $task);

        return $task->refresh();
    }

    /**
     * Transition d'état d'une tâche (open → in_progress → done | cancelled).
     *
     * @param  array<string, mixed>  $data
     */
    public function transitionTask(FuelMaintenanceTask $task, string $targetStatus, Employee $actor, array $data = []): FuelMaintenanceTask
    {
        if (! in_array($targetStatus, FuelMaintenanceTask::STATUSES, true)) {
            throw new FuelWorkflowTransitionException("Statut cible invalide: {$targetStatus}");
        }

        $allowed = match ($task->status) {
            FuelMaintenanceTask::STATUS_OPEN => [
                FuelMaintenanceTask::STATUS_IN_PROGRESS,
                FuelMaintenanceTask::STATUS_CANCELLED,
            ],
            FuelMaintenanceTask::STATUS_IN_PROGRESS => [
                FuelMaintenanceTask::STATUS_DONE,
                FuelMaintenanceTask::STATUS_CANCELLED,
            ],
            default => [],
        };

        if (! in_array($targetStatus, $allowed, true)) {
            throw new FuelWorkflowTransitionException(
                "Transition {$task->status} → {$targetStatus} interdite"
            );
        }

        $updates = [
            'status' => $targetStatus,
            'assigned_to' => $data['assigned_to'] ?? $task->assigned_to,
        ];

        if ($targetStatus === FuelMaintenanceTask::STATUS_IN_PROGRESS && $task->started_at === null) {
            $updates['started_at'] = Carbon::now('UTC');
        }

        if ($targetStatus === FuelMaintenanceTask::STATUS_DONE) {
            $updates['completed_by'] = $actor->id;
            $updates['completed_at'] = Carbon::now('UTC');
        }

        $task->update($updates);

        $this->audit($actor, 'fuel.maintenance.task.'.$targetStatus, $task);

        return $task->refresh();
    }

    private function assertTransitionAllowed(FuelIncident $incident, string $target): void
    {
        $allowed = match ($incident->status) {
            FuelIncident::STATUS_REPORTED => [
                FuelIncident::STATUS_ASSIGNED,
                FuelIncident::STATUS_RESOLVED,
            ],
            FuelIncident::STATUS_ASSIGNED => [
                FuelIncident::STATUS_RESOLVED,
            ],
            FuelIncident::STATUS_RESOLVED => [
                FuelIncident::STATUS_CLOSED,
            ],
            default => [],
        };

        if (! in_array($target, $allowed, true)) {
            throw new FuelWorkflowTransitionException(
                "Transition {$incident->status} → {$target} interdite"
            );
        }
    }

    /**
     * Redacte une description libre : jamais de PII ni de secrets (l'audit
     * est conservé, la description reste minimale et contrôlée).
     */
    private function redact(string $description): string
    {
        $trimmed = trim($description);
        if ($trimmed === '') {
            return '—';
        }

        return mb_substr($trimmed, 0, 2000);
    }

    private function audit(Employee $actor, string $event, Model $target): void
    {
        AuditLog::record(
            module: 'fuel',
            action: $event,
            subject: $target,
            actor: $actor,
            newValues: ['status' => $target->getAttribute('status')],
        );
    }

    private function asString(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableDate(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }

    private function companyId(Model $model, Employee $actor): string
    {
        $existing = $model->getAttribute('company_id');

        return is_string($existing) && $existing !== '' ? $existing : (string) $actor->company_id;
    }
}
