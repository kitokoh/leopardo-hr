<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTimetableSlot;

/**
 * Issue #5822 (EDU-006) — Policy des créneaux d'emploi du temps.
 *
 * - Gestion du tenant (principal/rh/manager) : CRUD complet, borné au
 *   tenant (`company_id`) — un gestionnaire ne voit JAMAIS un créneau d'un
 *   autre tenant.
 * - Enseignant : `viewAny` limité à SES créneaux (lien `EduTeacher` →
 *   `employee_id` — le modèle est livré par EDU-004 #5820) et `view` borné
 *   tenant : uniquement les créneaux qui lui sont affectés.
 * - Un employé sans rôle gestionnaire ni profil enseignant n'a aucun accès.
 */
class EduTimetableSlotPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        if ($actor->hasManagerRole(...self::MANAGER_ROLES)) {
            return true;
        }

        // Enseignant : autorisé à lister, mais le contrôleur borne la requête
        // à SES créneaux (teacher_id = profil EduTeacher lié à l'employé).
        return $this->teacherIdFor($actor) !== null;
    }

    public function view(Employee $actor, EduTimetableSlot $slot): bool
    {
        if ($slot->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->hasManagerRole(...self::MANAGER_ROLES)) {
            return true;
        }

        return $this->teacherIdFor($actor) === $slot->teacher_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function update(Employee $actor, EduTimetableSlot $slot): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES)
            && $slot->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, EduTimetableSlot $slot): bool
    {
        return $this->update($actor, $slot);
    }

    /**
     * Profil enseignant de l'employé (le cas échéant) dans SON tenant.
     */
    private function teacherIdFor(Employee $actor): ?int
    {
        /** @var EduTeacher|null $teacher */
        $teacher = EduTeacher::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->first();

        return $teacher?->id;
    }
}
