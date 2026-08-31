<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use Illuminate\Database\Eloquent\Model;

/**
 * Issue #5824 (EDU-008) — Policy des bulletins de période.
 *
 * Règles :
 *   - viewAny : gestionnaire du tenant UNIQUEMENT (`role === 'manager'` ou
 *     `manager_role` principal/rh) — ni enseignant, ni gardien ne liste
 *     les bulletins ;
 *   - view : gestionnaire du tenant, OU responsable légal explicitement lié
 *     à l'élève du bulletin avec `can_view_grades = true` (données
 *     sensibles, spec §6.1), OU enseignant de la classe (best-effort,
 *     refus si le lien n'est pas exploitable) ;
 *   - validate/publish : gestionnaire du tenant uniquement.
 *
 * Toujours borné au tenant (card.company_id === actor.company_id) : un
 * bulletin d'un autre tenant est invisible (fail-closed, jamais de fuite
 * PII hors tenant).
 *
 * EduTeacher/EduClass sont livrés par EDU-003 (#5819) : le rôle enseignant
 * reste best-effort — sans lien exploitable, refus (fail-closed). EduGuardian
 * / EduStudentGuardian (EDU-002, #5818) sont des dépendances dures (livrées).
 */
class EduReportCardPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $this->isManager($actor);
    }

    public function view(Employee $actor, EduReportCard $card): bool
    {
        if ($card->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        if ($this->guardianCanViewGrades($actor, $card)) {
            return true;
        }

        return $this->teachesClass($actor, (int) $card->class_id);
    }

    public function validate(Employee $actor, EduReportCard $card): bool
    {
        return $this->isManager($actor) && $card->company_id === $actor->company_id;
    }

    public function publish(Employee $actor, EduReportCard $card): bool
    {
        return $this->validate($actor, $card);
    }

    private function isManager(Employee $actor): bool
    {
        return $actor->role === 'manager' || $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    /**
     * Gardien autorisé : lien edu_student_guardians vers l'élève du bulletin
     * AVEC `can_view_grades = true`, via le dossier EduGuardian.employee_id
     * de l'employé connecté.
     */
    private function guardianCanViewGrades(Employee $actor, EduReportCard $card): bool
    {
        if ($card->company_id !== $actor->company_id) {
            return false;
        }

        $guardianId = $this->guardianIdFor($actor);

        if ($guardianId === null) {
            return false;
        }

        return EduStudentGuardian::query()
            ->where('company_id', $card->company_id)
            ->where('guardian_id', $guardianId)
            ->where('student_id', $card->student_id)
            ->where('can_view_grades', true)
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

    /**
     * Enseignant : lien EduTeacher → employee_id (fallback sûr : modèle non
     * livré → refus, jamais de fuite).
     */
    private function isTeacher(Employee $actor): bool
    {
        /** @var class-string<Model> $teacherModel */
        $teacherModel = 'App\Modules\EduManager\Domain\Models\EduTeacher';

        if (! class_exists($teacherModel)) {
            return false;
        }

        return $teacherModel::query()
            ->where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->exists();
    }

    /**
     * Best-effort : l'enseignant n'est autorisé que sur SES classes.
     *
     * Le lien enseignant → classe est porté par edu_timetable_slots (EDU-006,
     * #5822) : un enseignant a une séance dans la classe → il l'enseigne.
     * Repli sur une relation `classes()` si une version ultérieure l'apporte.
     * Sans lien exploitable, refus (fail-closed).
     */
    private function teachesClass(Employee $actor, int $classId): bool
    {
        if ($classId <= 0 || ! $this->isTeacher($actor)) {
            return false;
        }

        /** @var class-string<Model> $teacherModel */
        $teacherModel = 'App\Modules\EduManager\Domain\Models\EduTeacher';

        $teacher = $teacherModel::query()
            ->where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->first();

        if (! $teacher instanceof Model) {
            return false;
        }

        /** @var class-string<Model> $slotModel */
        $slotModel = 'App\Modules\EduManager\Domain\Models\EduTimetableSlot';

        if (class_exists($slotModel)) {
            return $slotModel::query()
                ->where('class_id', $classId)
                ->where('teacher_id', (int) $teacher->getKey())
                ->where('company_id', $actor->company_id)
                ->exists();
        }

        if (method_exists($teacher, 'classes')) {
            /** @var \Illuminate\Database\Eloquent\Builder $classesQuery */
            $classesQuery = $teacher->{'classes'}();

            return $classesQuery->whereKey($classId)->exists();
        }

        return false;
    }
}
