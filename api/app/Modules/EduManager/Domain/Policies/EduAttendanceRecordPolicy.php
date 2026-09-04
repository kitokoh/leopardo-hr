<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Issue #5821 (EDU-005) — Policy des enregistrements de présence scolaire.
 *
 * Règles :
 *   - viewAny : gestionnaire du tenant (`role === 'manager'` ou
 *     `manager_role` principal/rh) OU enseignant rattaché via un lien
 *     EduTeacher.employee_id — un enseignant ne voit QUE ses classes ;
 *   - view/update : bornés au tenant (record.company_id === actor.company_id) ;
 *     l'enseignant ne voit/modifie QUE les classes qui lui sont affectées ;
 *   - create : gestionnaire ou enseignant de la classe (best-effort).
 *
 * EduTeacher/EduClass sont livrés par EDU-003 (#5819) : tant qu'ils
 * n'existent pas, le rôle enseignant est REFUSÉ (fail-closed) et le rôle
 * gestionnaire reste pleinement fonctionnel. Les références aux modèles
 * non encore livrés passent par des noms de classe en chaîne (aucune
 * dépendance d'autoload sur un lot non livré).
 */
class EduAttendanceRecordPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh'];

    public function viewAny(Employee $actor): bool
    {
        return $this->isManager($actor) || $this->isTeacher($actor);
    }

    public function view(Employee $actor, EduAttendanceRecord $record): bool
    {
        if ($record->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        return $this->isTeacher($actor) && $this->teachesClass($actor, (int) $record->class_id);
    }

    public function create(Employee $actor, int $classId = 0): bool
    {
        if ($this->isManager($actor)) {
            return true;
        }

        return $this->isTeacher($actor) && $this->teachesClass($actor, $classId);
    }

    public function update(Employee $actor, EduAttendanceRecord $record): bool
    {
        return $this->view($actor, $record);
    }

    private function isManager(Employee $actor): bool
    {
        return $actor->role === 'manager' || $actor->hasManagerRole(...self::MANAGER_ROLES);
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
     * Repli sur une relation `classes()` si une version ultérieure d'EDU-003
     * l'apporte. Sans lien exploitable, refus (fail-closed).
     */
    private function teachesClass(Employee $actor, int $classId): bool
    {
        if ($classId <= 0) {
            return false;
        }

        /** @var class-string<Model> $teacherModel */
        $teacherModel = 'App\Modules\EduManager\Domain\Models\EduTeacher';

        if (! class_exists($teacherModel)) {
            return false;
        }

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
