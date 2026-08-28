<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Repositories;

use App\Modules\CRM\Domain\Contracts\CrmLeadRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Support\Facades\DB;

/**
 * #5717 — Implémentation Eloquent du port de persistance des leads.
 */
final class CrmLeadRepository implements CrmLeadRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?CrmLead
    {
        return CrmLead::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function markConverted(CrmLead $lead, string $convertedAt): bool
    {
        // Transition conditionnelle atomique : un lead déjà converti ne peut
        // pas être re-converti (idempotence, 409 côté action).
        return DB::table('crm_leads')
            ->where('id', $lead->id)
            ->where('company_id', $lead->company_id)
            ->where('status', '!=', CrmLeadStatus::Converted->value)
            ->update([
                'status' => CrmLeadStatus::Converted->value,
                'converted_at' => $convertedAt,
                'updated_at' => now(),
            ]) === 1;
    }
}
