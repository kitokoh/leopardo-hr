<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;

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
}
