<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduReportCard;

/**
 * #5825 (EDU-009) — bulletins : génération/validation/publication par la
 * direction ; lecture par l'enseignant de la classe (notes) ; le guardian
 * autorisé (can_view_grades) accède via le portail dédié (EDU-013).
 */
class EduReportCardPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduReportCard $card): bool
    {
        if ($card->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        // Enseignant : le bulletin n'est visible qu'une fois publié.
        if (! $card->isPublished()) {
            return false;
        }

        return true; // périmètre classe filtré au niveau requête (query scoping)
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function validate(Employee $actor, EduReportCard $card): bool
    {
        return $card->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }

    public function publish(Employee $actor, EduReportCard $card): bool
    {
        return $this->validate($actor, $card);
    }

    public function update(Employee $actor, EduReportCard $card): bool
    {
        return $this->validate($actor, $card);
    }
}
