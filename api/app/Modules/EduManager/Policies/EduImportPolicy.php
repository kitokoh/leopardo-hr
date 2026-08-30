<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduImport;

/**
 * #5833 (EDU-017) — imports/exports : direction uniquement (données PII
 * scolaires en masse).
 */
class EduImportPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function view(Employee $actor, EduImport $import): bool
    {
        return $import->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduImport $import): bool
    {
        return $this->view($actor, $import);
    }

    public function delete(Employee $actor, EduImport $import): bool
    {
        return $this->view($actor, $import);
    }
}
