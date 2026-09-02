<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmCampaign;

/**
 * Policy des campagnes marketing CRM — Issue #5724.
 *
 * Lecture / report : tout manager du tenant ; écritures et actions de cycle
 * de vie : `principal` / `marketing`. Fail-closed cross-tenant (#3232) —
 * aucune garde inline (constitution §V).
 */
class CrmCampaignPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, CrmCampaign $campaign): bool
    {
        if ($campaign->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager();
    }

    public function report(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->view($actor, $campaign);
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function update(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    public function delete(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    public function start(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    public function pause(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    public function resume(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    public function cancel(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    public function finish(Employee $actor, CrmCampaign $campaign): bool
    {
        return $this->isWritable($actor, $campaign);
    }

    private function isWritable(Employee $actor, CrmCampaign $campaign): bool
    {
        if ($campaign->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'marketing');
    }
}
