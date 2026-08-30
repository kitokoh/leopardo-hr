<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTeacherAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Aides RBAC communes du contexte EduManager (EDU-009, #5825).
 *
 * Rôles scolaires (least privilege) :
 * - direction/administration = manager du tenant (isManager) ;
 * - enseignant = employé lié dans edu_teachers (une classe = ses classes
 *   via edu_teacher_assignments) ;
 * - guardian = accès limité à SES enfants (lecture seule, notes publiées) —
 *   endpoint dédié guardian, non exposé ici.
 */
trait EduSchoolRoles
{
    /**
     * L'employé est enseignant dans ce tenant.
     */
    private function isTeacher(Employee $actor): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('edu_teachers')) {
            return false;
        }

        return EduTeacher::query()
            ->where('company_id', (string) $actor->company_id)
            ->where('employee_id', $actor->id)
            ->exists();
    }

    /**
     * L'enseignant est affecté à la classe (même tenant).
     */
    private function teachesClass(Employee $actor, int $classId): bool
    {
        if (! $this->isTeacher($actor)) {
            return false;
        }

        if (! DB::getSchemaBuilder()->hasTable('edu_teacher_assignments')) {
            return false;
        }

        return EduTeacherAssignment::query()
            ->where('company_id', (string) $actor->company_id)
            ->where('class_id', $classId)
            ->where('status', EduTeacherAssignment::STATUS_ACTIVE)
            ->whereHas('teacher', fn ($q) => $q->where('employee_id', $actor->id))
            ->exists();
    }

    /**
     * @return array<int, int> classes enseignées par l'employé (tenant courant)
     */
    public function taughtClassIds(Employee $actor): array
    {
        if (! DB::getSchemaBuilder()->hasTable('edu_teacher_assignments')) {
            return [];
        }

        return EduTeacherAssignment::query()
            ->where('company_id', (string) $actor->company_id)
            ->where('status', EduTeacherAssignment::STATUS_ACTIVE)
            ->whereHas('teacher', fn ($q) => $q->where('employee_id', $actor->id))
            ->pluck('class_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
