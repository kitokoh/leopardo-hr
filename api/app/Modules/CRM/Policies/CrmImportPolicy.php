<?php

declare(strict_types=1);

namespace App\Modules\CRM\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmImport;

/**
 * #5714 — Policy des imports CSV CRM.
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent les
 * imports CRM. Le CRM commercial Leopardo (Platform/Marketing) n'est jamais
 * concerné (ADR-CRM-001/002). Les Policies CRM affinées par rôle arrivent
 * avec CRM-V0-07 (#5711) ; celle-ci est le garde minimal de cette issue.
 */
class CrmImportPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmImport $import): bool
    {
        return $this->isCrmManager($actor) && $import->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function commit(Employee $actor, CrmImport $import): bool
    {
        return $this->view($actor, $import);
    }

    public function cancel(Employee $actor, CrmImport $import): bool
    {
        return $this->view($actor, $import);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }
}
