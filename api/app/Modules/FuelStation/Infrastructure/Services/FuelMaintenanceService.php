<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Events\FuelIncidentReported;
use App\Modules\FuelStation\Domain\Events\FuelIncidentResolved;
use App\Modules\FuelStation\Domain\Events\FuelMaintenanceTaskCompleted;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;

/**
 * Règles métier incidents & maintenance FuelStation (FUEL-010, #5804).
 *
 * - Workflow audité : reported → assigned → in_progress → resolved →
 *   closed, transitions validées par `FuelIncident::TRANSITIONS` ;
 *   `resolution_notes` obligatoire pour resolved.
 * - Incident rejouable : `idempotency_key` unique par tenant.
 * - Pièces jointes contrôlées : mime + taille validés à l'application,
 *   chemin serveur généré, jamais de chemin client.
 */
final class FuelMaintenanceService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    private const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array{incident: FuelIncident, replayed: bool}
     */
    public function createIncident(Employee $actor, FuelStation $station, array $data): array
    {
        $existing = FuelIncident::query()
            ->where('company_id', $station->company_id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing instanceof FuelIncident) {
            return ['incident' => $existing, 'replayed' => true];
        }

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $station->id,
            'equipment_type' => $data['equipment_type'],
            'equipment_id' => $data['equipment_id'] ?? null,
            'severity' => $data['severity'],
            'status' => FuelIncident::STATUS_REPORTED,
            'title' => $data['title'],
            'description' => $data['description'],
            'reported_by' => $actor->id,
            'assigned_to' => $data['assigned_to'] ?? null,
            'idempotency_key' => $data['idempotency_key'],
        ]);

        FuelIncidentReported::dispatch($incident);

        return ['incident' => $incident, 'replayed' => false];
    }

    /**
     * Transition de workflow avec garde — resolved requiert des notes de
     * résolution ; toute transition illégale est rejetée.
     *
     * @param  array{status: string, resolution_notes?: string|null, assigned_to?: int|null}  $data
     */
    public function transition(Employee $actor, FuelIncident $incident, array $data): FuelIncident
    {
        $target = $data['status'];

        if (! in_array($target, FuelIncident::TRANSITIONS[$incident->status] ?? [], true)) {
            throw new \InvalidArgumentException('Transition d\'incident non autorisée (workflow FuelStation).');
        }

        if ($target === FuelIncident::STATUS_RESOLVED) {
            $notes = $data['resolution_notes'] ?? null;

            if (! is_string($notes) || trim($notes) === '') {
                throw new \InvalidArgumentException('resolution_notes est obligatoire pour résoudre un incident.');
            }
        }

        $update = [
            'status' => $target,
            'assigned_to' => $data['assigned_to'] ?? $incident->assigned_to,
        ];

        if ($target === FuelIncident::STATUS_RESOLVED) {
            $update['resolution_notes'] = $data['resolution_notes'];
            $update['resolved_at'] = now();
        }

        $incident->update($update);
        $incident = $incident->refresh();

        if ($target === FuelIncident::STATUS_RESOLVED) {
            FuelIncidentResolved::dispatch($incident);
        }

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTask(Employee $actor, FuelStation $station, array $data): FuelMaintenanceTask
    {
        /** @var FuelMaintenanceTask $task */
        $task = FuelMaintenanceTask::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $data['station_id'] ?? $station->id,
            'incident_id' => $data['incident_id'] ?? null,
            'task_type' => $data['task_type'],
            'priority' => $data['priority'],
            'status' => FuelMaintenanceTask::STATUS_PENDING,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        return $task;
    }

    /**
     * @param  array{status: string, notes?: string|null}  $data
     */
    public function updateTask(Employee $actor, FuelMaintenanceTask $task, array $data): FuelMaintenanceTask
    {
        $target = $data['status'];

        if (! in_array($target, FuelMaintenanceTask::STATUSES, true)) {
            throw new \InvalidArgumentException('Statut de tâche invalide.');
        }

        $update = ['status' => $target];

        if ($target === FuelMaintenanceTask::STATUS_DONE) {
            $update['completed_by'] = $actor->id;
            $update['completed_at'] = now();
        }

        if ($target === FuelMaintenanceTask::STATUS_CANCELLED) {
            $update['completed_at'] = null;
        }

        if (isset($data['notes'])) {
            $update['notes'] = $data['notes'];
        }

        $task->update($update);
        $task = $task->refresh();

        if ($target === FuelMaintenanceTask::STATUS_DONE) {
            FuelMaintenanceTaskCompleted::dispatch($task);
        }

        return $task;
    }

    /**
     * Pièce jointe contrôlée — mime + taille validés AVANT écriture.
     *
     * @return array{path: string, original_name: string, mime_type: string, size_bytes: int}
     */
    public function attachFile(Employee $actor, FuelIncident $incident, UploadedFile $file): array
    {
        $mime = $file->getMimeType();

        if (! is_string($mime) || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé (jpg, png, pdf).');
        }

        if ($file->getSize() > self::MAX_ATTACHMENT_BYTES) {
            throw new \InvalidArgumentException('Fichier trop volumineux (max 5 Mo).');
        }

        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $path = $file->storeAs(
            "fuel_incidents/{$incident->company_id}/{$incident->id}",
            sprintf('%s.%s', (string) str()->uuid(), $extension),
            'local',
        );

        if (! is_string($path)) {
            throw new \RuntimeException('Impossible de stocker la pièce jointe.');
        }

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
        ];
    }
}
