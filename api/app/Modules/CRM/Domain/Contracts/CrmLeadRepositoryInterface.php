<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * #5717 — Port de persistance des leads CRM (tenant-scoped).
 */
interface CrmLeadRepositoryInterface
{
    /**
     * Charge un lead scopé au tenant. null si absent OU hors tenant (404 sûr).
     */
    public function findForCompany(int $id, string $companyId): ?CrmLead;

    /**
     * Marque un lead converti (claim conditionnel : échoue si déjà converti).
     */
    public function markConverted(CrmLead $lead, string $convertedAt): bool;
}
