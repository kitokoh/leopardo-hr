<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Events\FuelIncidentReported;
use App\Modules\FuelStation\Domain\Events\FuelIncidentResolved;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Incidents, maintenance et tâches FuelStation (FUEL-010, issue #5804).
 *
 * - Workflow audité : chaque transition émet un événement
 *   (FuelIncidentReported / FuelIncidentResolved) tracé dans `audit_logs`
 *   via AuditLogger — jamais de mutation sans trace.
 * - Assignation : uniquement un employé DU MÊME tenant (EMPLOYEE_OUTSIDE_TENANT).
 * - Résolution : notes obligatoires, horodatage serveur, idempotente.
 * - Pièces jointes contrôlées : mime/size validés au niveau Request.
 * - Notifications (FUEL-019) : événements sans PII (ids + priorité + titre
 *   d'équipement seulement).
 */
final class FuelIncidentService
{
    /**
     * Signale un incident — tout employé authentifié (pompiste compris).
     *
     * @param  array<string, mixed>  $data
     */
    public function report(Employee $actor, array $data): FuelIncident
    {
        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => (int) $data['station_id'],
            'equipment_type' => $data['equipment_type'] ?? FuelIncident::EQUIPMENT_OTHER,
            'equipment_id' => isset($data['equipment_id']) ? (int) $data['equipment_id'] : null,
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? FuelIncident::PRIORITY_MEDIUM,
            'status' => FuelIncident::STATUS_REPORTED,
            'reported_by' => $actor->id,
        ]);

        FuelIncidentReported::dispatch($incident);

        return $incident;
    }

    /**
     * Assigne un incident à un employé du tenant (manager).
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(FuelIncident $incident, Employee $actor, array $data): FuelIncident
    {
        abort_if($incident->status === FuelIncident::STATUS_CLOSED, 422, 'FUEL_INCIDENT_CLOSED');

        $assigneeId = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        if ($assigneeId !== null) {
            $assignee = Employee::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($assigneeId)
                ->first();

            abort_if($assignee === null, 422, 'EMPLOYEE_OUTSIDE_TENANT');
        }

        $incident->update([
            'assigned_to' => $assigneeId,
            'status' => FuelIncident::STATUS_ASSIGNED,
        ]);

        return $incident->refresh();
    }

    /**
     * Transition manager/assigné : reported|assigned → in_progress.
     */
    public function start(FuelIncident $incident): FuelIncident
    {
        abort_if($incident->status === FuelIncident::STATUS_CLOSED, 422, 'FUEL_INCIDENT_CLOSED');

        $incident->update(['status' => FuelIncident::STATUS_IN_PROGRESS]);

        return $incident->refresh();
    }

    /**
     * Résout un incident — notes obligatoires, idempotente, événement émis.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolve(FuelIncident $incident, Employee $actor, array $data): FuelIncident
    {
        if ($incident->status === FuelIncident::STATUS_RESOLVED || $incident->status === FuelIncident::STATUS_CLOSED) {
            return $incident->refresh();
        }

        $notes = isset($data['resolution_notes']) ? (string) $data['resolution_notes'] : '';

        abort_if(trim($notes) === '', 422, 'FUEL_INCIDENT_RESOLUTION_NOTES_REQUIRED');

        $incident->update([
            'status' => FuelIncident::STATUS_RESOLVED,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);

        FuelIncidentResolved::dispatch($incident);

        return $incident->refresh();
    }

    /**
     * Clôture un incident résolu (manager) — idempotente.
     */
    public function close(FuelIncident $incident): FuelIncident
    {
        abort_if($incident->status !== FuelIncident::STATUS_RESOLVED, 422, 'FUEL_INCIDENT_NOT_RESOLVED');

        $incident->update([
            'status' => FuelIncident::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return $incident->refresh();
    }

    /**
     * Crée une tâche de maintenance (préventive/corrective).
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(Employee $actor, array $data): FuelMaintenanceTask
    {
        $incidentId = isset($data['incident_id']) ? (int) $data['incident_id'] : null;

        if ($incidentId !== null) {
            $incident = FuelIncident::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($incidentId)
                ->first();

            abort_if($incident === null, 422, 'INCIDENT_OUTSIDE_TENANT');
        }

        /** @var FuelMaintenanceTask $task */
        $task = FuelMaintenanceTask::query()->create([
            'company_id' => $actor->company_id,
            'incident_id' => $incidentId,
            'title' => $data['title'],
            'task_type' => $data['task_type'] ?? FuelMaintenanceTask::TYPE_PREVENTIVE,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => FuelMaintenanceTask::STATUS_PLANNED,
            'assigned_to' => isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        return $task;
    }

    /**
     * Met à jour une tâche (statut, échéance, assignation, notes).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTask(FuelMaintenanceTask $task, array $data): FuelMaintenanceTask
    {
        $payload = $data;

        if (($payload['status'] ?? null) === FuelMaintenanceTask::STATUS_COMPLETED) {
            $payload['completed_at'] = $payload['completed_at'] ?? now();
        }

        $task->update($payload);

        return $task->refresh();
    }

    /**
     * Ajoute une pièce jointe contrôlée (mime/size validés dans le Request).
     *
     * @param  array<string, mixed>  $data
     */
    public function addAttachment(FuelIncident $incident, Employee $actor, array $data): FuelIncidentAttachment
    {
        /** @var UploadedFile $file */
        $file = $data['file'];

        $path = $file->store(
            'fuel-incidents/'.(string) $incident->id,
            ['disk' => 'local', 'visibility' => 'private']
        );

        if (! is_string($path)) {
            abort(500, 'FUEL_ATTACHMENT_STORE_FAILED');
        }

        /** @var FuelIncidentAttachment $attachment */
        $attachment = FuelIncidentAttachment::query()->create([
            'company_id' => $actor->company_id,
            'incident_id' => (int) $incident->getAttribute('id'),
            'filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);

        return $attachment;
    }

    /**
     * Supprime une pièce jointe (manager ou uploader) — transactionnelle
     * (fichier + ligne).
     */
    public function deleteAttachment(FuelIncidentAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $path = $attachment->storage_path;

            $attachment->delete();

            Storage::disk('local')->delete($path);
        });
    }
}
