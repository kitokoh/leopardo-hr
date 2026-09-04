<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;

/**
 * #5818 (EDU-002) — Policy des élèves.
 *
 * - Gestion du tenant (principal/rh/manager) : accès complet, borné au
 *   tenant (`company_id`).
 * - Responsable légal (gardien) : accès UNIQUEMENT aux élèves explicitement
 *   liés dans `edu_student_guardians` — « tests guardian non autorisé » :
 *   un gardien ne voit jamais les élèves d'un autre gardien, ni un élève
 *   d'un autre tenant.
 * - `viewGrades` : réservé aux gestionnaires et aux gardiens avec
 *   `can_view_grades = true` sur le lien (données sensibles, spec §6.1).
 */
class EduStudentPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduStudent $student): bool
    {
        if ($this->viewAny($actor)) {
            return $student->company_id === $actor->company_id;
        }

        return $this->guardianCanView($actor, $student);
    }

    public function viewGrades(Employee $actor, EduStudent $student): bool
    {
        if ($this->viewAny($actor)) {
            return $student->company_id === $actor->company_id;
        }

        if ($student->company_id !== $actor->company_id) {
            return false;
        }

        $guardianId = $this->guardianIdFor($actor);

        if ($guardianId === null) {
            return false;
        }

        return EduStudentGuardian::query()
            ->where('company_id', $student->company_id)
            ->where('guardian_id', $guardianId)
            ->where('student_id', $student->id)
            ->where('can_view_grades', true)
            ->exists();
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduStudent $student): bool
    {
        return $this->viewAny($actor) && $student->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, EduStudent $student): bool
    {
        return $this->update($actor, $student);
    }

    private function guardianCanView(Employee $actor, EduStudent $student): bool
    {
        if ($student->company_id !== $actor->company_id) {
            return false;
        }

        $guardianId = $this->guardianIdFor($actor);

        if ($guardianId === null) {
            return false;
        }

        return EduStudentGuardian::query()
            ->where('company_id', $student->company_id)
            ->where('guardian_id', $guardianId)
            ->where('student_id', $student->id)
            ->exists();
    }

    private function guardianIdFor(Employee $actor): ?int
    {
        /** @var EduGuardian|null $guardian */
        $guardian = EduGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->first();

        return $guardian?->id;
    }
}
