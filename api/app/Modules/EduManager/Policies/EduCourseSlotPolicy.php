<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduCourseSlot;

/**
 * #5825 (EDU-009) — emplois du temps : la direction gère les créneaux ;
 * les enseignants consultent (leur emploi du temps = leurs classes).
 */
class EduCourseSlotPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor);
    }

    public function view(Employee $actor, EduCourseSlot $slot): bool
    {
        if ($slot->company_id !== $actor->company_id) {
            return false;
        }

        if (EduAccess::isAdmin($actor)) {
            return true;
        }

        return $slot->teacher_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduCourseSlot $slot): bool
    {
        return $this->view($actor, $slot) && EduAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, EduCourseSlot $slot): bool
    {
        return $this->update($actor, $slot);
    }
}
