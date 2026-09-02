<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTimetableSlot;

/**
 * Issue #5823 (EDU-007) — Policy des évaluations.
 *
 * - viewAny : gestionnaire du tenant (role manager + manager_role
 *   principal/rh/manager) OU enseignant rattaché via un lien
 *   EduTeacher.employee_id ;
 * - create/update/delete : gestionnaires du tenant uniquement, bornés au
 *   tenant (`company_id`) — un gestionnaire ne voit JAMAIS une évaluation
 *   d'un autre tenant ;
 * - publish : acte d'administration (verrouille les notes) — réservé aux
 *   gestionnaires du tenant ;
 * - view : gestionnaire (tenant) OU enseignant de la CLASSE de l'évaluation
 *   (lien EduTeacher → emploi du temps, best-effort : sans lien exploitable,
 *   refus fail-closed).
 *
 * Un employé sans rôle gestionnaire ni profil enseignant n'a aucun accès.
 */
class EduAssessmentPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $this->isManager($actor) || $this->isTeacher($actor);
    }

    public function view(Employee $actor, EduAssessment $assessment): bool
    {
        if ($assessment->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        return $this->isTeacher($actor) && $this->teachesClass($actor, (int) $assessment->class_id);
    }

    public function create(Employee $actor): bool
    {
        return $this->isManager($actor);
    }

    public function update(Employee $actor, EduAssessment $assessment): bool
    {
        return $this->isManager($actor) && $assessment->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, EduAssessment $assessment): bool
    {
        return $this->update($actor, $assessment);
    }

    /**
     * Publication de l'évaluation (GradeService::publishAssessment) :
     * verrouille les notes — acte d'administration, gestionnaire du tenant.
     */
    public function publish(Employee $actor, EduAssessment $assessment): bool
    {
        return $this->update($actor, $assessment);
    }

    private function isManager(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    /**
     * Enseignant : lien EduTeacher → employee_id dans SON tenant.
     */
    private function isTeacher(Employee $actor): bool
    {
        return EduTeacher::query()
            ->where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->exists();
    }

    /**
     * Best-effort : l'enseignant n'est autorisé que sur SES classes.
     *
     * Le lien enseignant → classe est porté par edu_timetable_slots (EDU-006,
     * #5822) : un enseignant a une séance dans la classe → il l'enseigne.
     * Sans lien exploitable, refus (fail-closed).
     */
    private function teachesClass(Employee $actor, int $classId): bool
    {
        if ($classId <= 0) {
            return false;
        }

        /** @var EduTeacher|null $teacher */
        $teacher = EduTeacher::query()
            ->where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->first();

        if (! $teacher instanceof EduTeacher) {
            return false;
        }

        return EduTimetableSlot::query()
            ->where('class_id', $classId)
            ->where('teacher_id', (int) $teacher->id)
            ->where('company_id', $actor->company_id)
            ->exists();
    }
}
