<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Repositories;

use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmImportStatus;
use App\Modules\CRM\Domain\Models\CrmImport;
use Illuminate\Support\Facades\DB;

/**
 * #5714 — Implémentation Eloquent du port de persistance des imports CRM.
 *
 * Les claims (commit/cancel) sont des UPDATE conditionnels atomiques :
 * deux requêtes concurrentes ne peuvent pas committer deux fois la même
 * session (idempotence, critère d'acceptation #5714).
 */
final class CrmImportRepository implements CrmImportRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?CrmImport
    {
        return CrmImport::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function createForCompany(string $companyId, int $actorId, array $attributes): CrmImport
    {
        return CrmImport::query()->create(array_merge($attributes, [
            'company_id' => $companyId,
            'created_by' => $actorId,
        ]));
    }

    public function claimCommit(CrmImport $import): bool
    {
        return $this->claimStatus($import, CrmImportStatus::Committing, CrmImportStatus::committableStatuses());
    }

    public function claimCancel(CrmImport $import): bool
    {
        return $this->claimStatus($import, CrmImportStatus::Cancelled, [
            CrmImportStatus::Previewed->value,
            CrmImportStatus::Committing->value,
        ]);
    }

    public function markCommitted(CrmImport $import, int $actorId, array $result): void
    {
        $import->forceFill([
            'status' => CrmImportStatus::Committed,
            'committed_by' => $actorId,
            'committed_at' => now(),
            'result' => $result,
        ])->save();
    }

    public function markFailed(CrmImport $import, array $result): void
    {
        $import->forceFill([
            'status' => CrmImportStatus::Failed,
            'result' => $result,
        ])->save();
    }

    /**
     * @param  list<string>  $fromStatuses  statuts sources acceptés pour la transition
     */
    private function claimStatus(CrmImport $import, CrmImportStatus $to, array $fromStatuses): bool
    {
        return DB::table('crm_imports')
            ->where('id', $import->id)
            ->where('company_id', $import->company_id)
            ->whereIn('status', $fromStatuses)
            ->update(['status' => $to->value, 'updated_at' => now()]) === 1;
    }
}
