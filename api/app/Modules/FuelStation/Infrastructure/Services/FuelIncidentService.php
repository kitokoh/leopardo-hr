<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Core\Auth\Domain\Models\AuditLog;use App\Modules\FuelStation\Domain\Exceptions\FuelWorkflowTransitionException;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Carbon;
use App\Modules\FuelStation\Domain\Exceptions\FuelWorkflowTransitionException;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Carbon;

/**
 * Incidents, maintenance et tâches FuelStation — FUEL-010 (issue #5804).
 *
 * Workflow audité (open → in_progress → resolved → closed) : chaque
 * transition est horodatée et attribuée. Pièces jointes contrôlées
 * (MIME/size allowlist — métadonnées uniquement). Notification à la
 * création SANS PII via l'outbox (fuel.incident.reported.v1, consommé par
 * FUEL-019).
 */
final class FuelIncidentService
{
    public function __construct(private readonly FuelOutboxPublisher $outbox) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function report(Employee $actor, array $data): FuelIncident
    {
        $incident = FuelIncident::query()->create([
            'company_id' => (string) $actor->company_id,
            'station_id' => $data['station_id'] ?? null,
            'equipment_type' => $data['equipment_type'] ?? FuelIncident::EQUIPMENT_OTHER,
            'equipment_id' => $data['equipment_id'] ?? null,
            'severity' => $data['severity'] ?? FuelIncident::SEVERITY_MEDIUM,
            'status' => FuelIncident::STATUS_OPEN,
            'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
            'description' => $data['description'] ?? null,
            'reported_by' => $actor->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        $this->outbox->publish(
            (string) $incident->company_id,
            FuelOutboxEvent::EVENT_INCIDENT_REPORTED,
            [
                'incident_id' => $incident->id,
                'station_id' => $incident->station_id,
                'severity' => $incident->severity,
                // Aucune PII : pas de nom/description dans l'événement.
            ],
            'fuel_incident',
            (string) $incident->id,
            'incident-'.$incident->id,
        );

        return $incident->refresh();
    }

    public function assign(Employee $actor, FuelIncident $incident, int $assigneeId): FuelIncident
    {
        $this->assertTenant($actor, $incident);

        $incident->forceFill([
            'assigned_to' => $assigneeId,
            'status' => FuelIncident::STATUS_IN_PROGRESS,
        ])->save();

        return $incident->refresh();
    }

    public function resolve(Employee $actor, FuelIncident $incident, string $notes): FuelIncident
    {
        $this->assertTenant($actor, $incident);

        $incident->forceFill([
            'status' => FuelIncident::STATUS_RESOLVED,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ])->save();

        return $incident->refresh();
    }

    public function close(Employee $actor, FuelIncident $incident, string $notes): FuelIncident
    {
        $this->assertTenant($actor, $incident);

        $incident->forceFill([
            'status' => FuelIncident::STATUS_CLOSED,
            'closed_by' => $actor->id,
            'closed_at' => now(),
            'closure_notes' => $notes,
        ])->save();

        return $incident->refresh();
    }

    /**
     * Pièce jointe contrôlée (métadonnées uniquement, MIME/size allowlist).
     *
     * @return array{attachment: FuelIncidentAttachment, allowed: bool}
     */
    public function attach(Employee $actor, FuelIncident $incident, string $fileName, string $mimeType, int $sizeBytes): array
    {
        $this->assertTenant($actor, $incident);

        $allowed = in_array($mimeType, FuelIncidentAttachment::ALLOWED_MIME_TYPES, true)
            && $sizeBytes <= FuelIncidentAttachment::MAX_SIZE_BYTES;

        // Métadonnées contrôlées : nom assaini (basename, sans chemin).
        $safeName = basename($fileName);

        $attachment = FuelIncidentAttachment::query()->create([
            'company_id' => (string) $actor->company_id,
            'incident_id' => $incident->id,
            'file_name' => $safeName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'uploaded_by' => $actor->id,
        ]);

        return ['attachment' => $attachment, 'allowed' => $allowed];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTask(Employee $actor, array $data): FuelMaintenanceTask
    {
        return FuelMaintenanceTask::query()->create([
            'company_id' => (string) $actor->company_id,
            'station_id' => $data['station_id'] ?? null,
            'incident_id' => $data['incident_id'] ?? null,
            'task_type' => $data['task_type'] ?? 'preventive',
            'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => FuelMaintenanceTask::STATUS_TODO,
            'assigned_to' => $data['assigned_to'] ?? null,
            'scheduled_for' => $data['scheduled_for'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTask(Employee $actor, FuelMaintenanceTask $task, array $data): FuelMaintenanceTask
    {
        $this->assertTenant($actor, $task);

        $fill = [
            'title' => $data['title'] ?? $task->title,
            'description' => $data['description'] ?? $task->description,
            'priority' => $data['priority'] ?? $task->priority,
            'assigned_to' => $data['assigned_to'] ?? $task->assigned_to,
            'scheduled_for' => $data['scheduled_for'] ?? $task->scheduled_for,
        ];

        if (isset($data['status']) && is_string($data['status'])) {
            $fill['status'] = $data['status'];

            if ($data['status'] === FuelMaintenanceTask::STATUS_DONE) {
                $fill['completed_at'] = now()->toDateString();
                $fill['completed_by'] = $actor->id;
            }
        }

        $task->forceFill($fill)->save();

        return $task->refresh();
    }

    private function assertTenant(Employee $actor, FuelIncident|FuelMaintenanceTask $model): void
    {
        if ($model->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

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