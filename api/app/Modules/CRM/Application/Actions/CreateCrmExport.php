<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\CreateCrmExportDTO;
use App\Modules\CRM\Domain\Enums\CrmExportEntity;
use App\Modules\CRM\Domain\Exceptions\CrmExportEntityUnavailableException;
use App\Modules\CRM\Domain\Models\CrmExportJob;
use App\Modules\CRM\Infrastructure\Jobs\ExportCrmDataJob;
use App\Modules\CRM\Infrastructure\Services\CrmExportColumns;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : créer un job d'export CRM (issue #5729).
 *
 * Valide l'entité + les colonnes (allowlist), persiste le job (queued) et
 * dispatch la génération asynchrone. `expires_at` est posé à la création.
 */
final class CreateCrmExport
{
    public function execute(CreateCrmExportDTO $dto, ?string $userId): CrmExportJob
    {
        if (! CrmExportEntity::isValid($dto->entity)) {
            throw new \App\Modules\CRM\Domain\Exceptions\CrmExportInvalidRequestException('Entite d\'export inconnue.');
        }

        $allowed = CrmExportColumns::allowedFor($dto->entity);
        foreach ($dto->columns as $column) {
            if (! in_array($column, $allowed, true)) {
                throw new \App\Modules\CRM\Domain\Exceptions\CrmExportInvalidRequestException('Colonne non allowlistee pour cette entite : '.$column);
            }
        }

        // L'entité doit être disponible (socle V0) — sinon l'export échouerait
        // en tâche de fond sans message clair.
        if (! $this->entityTableExists($dto->entity)) {
            throw new CrmExportEntityUnavailableException();
        }

        $job = CrmExportJob::query()->create([
            'user_id' => $userId,
            'entity' => $dto->entity,
            'format' => 'csv',
            'filters' => $dto->filters,
            'columns' => $dto->columns,
            'status' => 'queued',
            'progress' => 0,
            'expires_at' => Carbon::now()->addHours((int) config('crm.exports.ttl_hours', 24)),
        ]);

        ExportCrmDataJob::dispatch((string) $job->id)
            ->onQueue((string) config('crm.exports.queue', 'default'));

        return $job;
    }

    private function entityTableExists(string $entity): bool
    {
        $table = [
            CrmExportEntity::ACCOUNTS => 'crm_accounts',
            CrmExportEntity::CONTACTS => 'crm_contacts',
            CrmExportEntity::LEADS => 'crm_leads',
            CrmExportEntity::OPPORTUNITIES => 'crm_opportunities',
            CrmExportEntity::ACTIVITIES => 'crm_activities',
            CrmExportEntity::TASKS => 'crm_tasks',
        ][$entity] ?? null;

        return $table !== null && \Illuminate\Support\Facades\Schema::hasTable($table);
    }
}
