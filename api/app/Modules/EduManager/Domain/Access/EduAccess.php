<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Access;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use Illuminate\Database\Eloquent\Collection;

/**
 * Socle RBAC EduManager — EDU-009 (issue #5825).
 *
 * Rôles scolaires (V0, portail employee) :
 * - Administration / direction : manager avec manager_role principal|rh,
 *   ou manager sans sous-rôle (propriétaire). Gestion complète.
 * - Enseignant : employé qui enseigne (classes référentes `teacher_id` ou
 *   affectations `edu_teacher_subjects`). Périmètre = ses classes.
 * - Responsable légal (guardian) : accès portail dédié (EDU-013) via la
 *   table edu_student_guardians — PAS un Employee ; les policies le
 *   vérifient via `isGuardianOf()` quand le portail existera.
 *
 * Confidentialité scolaire : les notes/bulletins ne sont visibles que par
 * l'enseignant de la classe, la direction et le guardian autorisé
 * (can_view_grades) — jamais par un employé hors périmètre.
 */
final class EduAccess
{
    /**
     * Direction / administration scolaire (gestion complète).
     */
    public static function isAdmin(Employee $actor): bool
    {
        if (! $actor->isManager()) {
            return false;
        }

        $role = $actor->manager_role;

        return $role === null || in_array($role, ['principal', 'rh'], true);
    }

    /**
     * Enseignant au sens scolaire (enseigne au moins une classe).
     */
    public static function isTeacher(Employee $actor): bool
    {
        return self::teacherClassIds($actor)->isNotEmpty()
            || EduClass::query()
                ->where('company_id', $actor->company_id)
                ->where('teacher_id', $actor->id)
                ->exists();
    }

    /**
     * Ids des classes enseignées par l'acteur (référentes + affectations).
     *
     * @return Collection<int, int>
     */
    public static function teacherClassIds(Employee $actor): Collection
    {
        $fromAssignments = EduTeacherSubject::query()
            ->where('company_id', $actor->company_id)
            ->where('teacher_id', $actor->id)
            ->where('status', EduTeacherSubject::STATUS_ACTIVE)
            ->pluck('class_id');

        $fromReferral = EduClass::query()
            ->where('company_id', $actor->company_id)
            ->where('teacher_id', $actor->id)
            ->pluck('id');

        return $fromAssignments->merge($fromReferral)->unique()->values();
    }

    /**
     * L'acteur peut-il gérer (écrire) cette classe ?
     */
    public static function canManageClass(Employee $actor, EduClass $class): bool
    {
        if ($class->company_id !== $actor->company_id) {
            return false;
        }

        return self::isAdmin($actor) || self::teacherClassIds($actor)->contains((int) $class->getAttribute('id'));
    }

    /**
     * L'acteur peut-il LIRE cette classe ?
     */
    public static function canViewClass(Employee $actor, EduClass $class): bool
    {
        return self::canManageClass($actor, $class);
    }
}
