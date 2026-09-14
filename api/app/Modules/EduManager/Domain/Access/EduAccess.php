<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Access;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use App\Modules\EduManager\Domain\Models\EduTimetableSlot;
use Illuminate\Support\Collection;

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
        return self::teacherClassIds($actor)->isNotEmpty();
    }

    /**
     * Identifiants sous lesquels l'acteur peut apparaître comme enseignant.
     *
     * Le dépôt connaît DEUX conventions historiques : l'identifiant d'EMPLOYÉ
     * (référent de classe `edu_classes.teacher_id`, séances d'emploi du temps)
     * et l'identifiant de l'ENSEIGNANT projeté (`edu_teachers.id`, cible des FK
     * composites). Les deux sont acceptées — sinon un enseignant « pur »
     * (role='employee' + `edu_teachers`) ou un référent de classe était vu
     * comme un employé quelconque, et les policies lui refusaient sa propre
     * classe (constaté sur `EduRbacPolicyTest`, `EduRbacMatrixTest`).
     *
     * @return list<int>
     */
    public static function teacherIdentifiers(Employee $actor): array
    {
        $identifiers = [(int) $actor->getAttribute('id')];

        /** @var Collection<int, int|string> $projections */
        $projections = EduTeacher::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->pluck('id');

        foreach ($projections as $projection) {
            $identifiers[] = (int) $projection;
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Ids des classes enseignées par l'acteur : référent de classe, affectation
     * matière/classe (EDU-003) et séance d'emploi du temps (EDU-006).
     *
     * @return Collection<int, int>
     */
    public static function teacherClassIds(Employee $actor): Collection
    {
        $identifiers = self::teacherIdentifiers($actor);
        $companyId = $actor->company_id;

        $fromAssignments = EduTeacherSubject::query()
            ->where('company_id', $companyId)
            ->whereIn('teacher_id', $identifiers)
            ->where('status', EduTeacherSubject::STATUS_ACTIVE)
            ->pluck('class_id');

        $fromSlots = EduTimetableSlot::query()
            ->where('company_id', $companyId)
            ->whereIn('teacher_id', $identifiers)
            ->pluck('class_id');

        $fromReferral = EduClass::query()
            ->where('company_id', $companyId)
            ->whereIn('teacher_id', $identifiers)
            ->pluck('id');

        return $fromAssignments
            ->merge($fromSlots)
            ->merge($fromReferral)
            ->filter(fn (mixed $classId): bool => (int) $classId > 0)
            ->map(fn (mixed $classId): int => (int) $classId)
            ->unique()
            ->values();
    }

    /**
     * L'acteur enseigne-t-il cette classe ?
     */
    public static function teachesClass(Employee $actor, int $classId): bool
    {
        return $classId > 0 && self::teacherClassIds($actor)->contains($classId);
    }

    /**
     * L'acteur est-il TITULAIRE (référent) d'une classe — ou de celle-ci ?
     *
     * Distinction métier : le titulaire d'une classe conduit la pédagogie de
     * cette classe (il crée et modifie ses évaluations), alors qu'un
     * enseignant qui n'y assure qu'une séance la LIT sans l'administrer.
     * Vérifié par les deux tests jumeaux `EduRbacPolicyTest::
     * test_teacher_can_create_assessment_and_grade_for_own_class` (titulaire →
     * autorisé) et `EduGradeTest::test_assessment_policy_allows_teacher_of_the_
     * class` (enseignant de séance → refusé).
     */
    public static function isClassReferent(Employee $actor, ?int $classId = null): bool
    {
        $query = EduClass::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('teacher_id', self::teacherIdentifiers($actor));

        if ($classId !== null) {
            $query->whereKey($classId);
        }

        return $query->exists();
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
