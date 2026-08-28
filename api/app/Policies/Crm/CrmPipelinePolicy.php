<?php

declare(strict_types=1);

namespace App\Policies\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmPipeline;

/**
 * Policy d'accès aux pipelines CRM client — Issue #5711 (CRM-V0-07).
 *
 * Les pipelines/étapes sont du paramétrage commercial du tenant : réservés
 * aux managers `principal`/`rh`. Pas d'accès « propriétaire » (les pipelines
 * n'ont pas de propriétaire individuel en V0).
 */
class CrmPipelinePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmPipeline $pipeline): bool
    {
        return $this->isCrmManager($actor);
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function update(Employee $actor, CrmPipeline $pipeline): bool
    {
        return $this->isCrmManager($actor);
    }

    public function delete(Employee $actor, CrmPipeline $pipeline): bool
    {
        return $this->isCrmManager($actor);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->isManager() && in_array($actor->manager_role, ['principal', 'rh'], true);
    }
}
