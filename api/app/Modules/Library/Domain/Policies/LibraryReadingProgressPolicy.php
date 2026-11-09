<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Library\Domain\Models\LibraryReadingProgress;

/**
 * RBAC de la progression de lecture (BC-40 LIBRARY).
 *
 * Règle unique et stricte : un employé ne peut lire ou écrire QUE sa propre
 * progression, et uniquement dans son propre tenant. `deny-by-default` : toute
 * autre combinaison est refusée.
 *
 * ⚠️ La progression est une donnée PERSONNELLE (comportement de lecture) : elle
 * n'est jamais exposée à un autre employé, même du même tenant, ni à un
 * responsable. Seul le propriétaire y accède.
 */
class LibraryReadingProgressPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, LibraryReadingProgress $progress): bool
    {
        return $this->owns($actor, $progress);
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function update(Employee $actor, LibraryReadingProgress $progress): bool
    {
        return $this->owns($actor, $progress);
    }

    public function delete(Employee $actor, LibraryReadingProgress $progress): bool
    {
        return $this->owns($actor, $progress);
    }

    /**
     * Le lecteur est-il le propriétaire de cette progression, dans son tenant ?
     */
    private function owns(Employee $actor, LibraryReadingProgress $progress): bool
    {
        return (int) $progress->employee_id === (int) $actor->id
            && (string) $progress->company_id === (string) $actor->company_id;
    }
}
