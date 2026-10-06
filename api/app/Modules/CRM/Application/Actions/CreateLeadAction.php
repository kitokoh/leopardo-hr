<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * #8195 / #5731 / #6570 — Création d'un prospect (lead) CRM client (tenant-scoped).
 */
final class CreateLeadAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Employee $actor, array $data): CrmLead
    {
        /** @var CrmLead $lead */
        $lead = CrmLead::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
        ]));

        return $lead;
    }
}
