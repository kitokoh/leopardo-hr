<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\JobPosting;

/**
 * Cas d'usage : création d'une offre d'emploi (statut initial brouillon).
 */
class CreateJobPostingAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés (sans company_id/created_by)
     */
    public function execute(int $companyId, int $createdById, array $data): JobPosting
    {
        return JobPosting::create([
            ...$data,
            'company_id' => $companyId,
            'created_by' => $createdById,
            'status' => 'draft',
        ]);
    }
}
