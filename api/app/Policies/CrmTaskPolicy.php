<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmTask;

/**
 * Issue #5711 — Policy des tâches CRM client (tenant).
 *
 * Les managers `principal`/`rh`/`marketing` gèrent toutes les tâches du
 * tenant. Un employé non-manager peut VOIR et FAIRE ÉVOLUER (statut /
 * complétion) les tâches qui lui sont assignées (`assigned_to`) — modèle
 * d'ownership partagé. La suppression reste réservée aux managers.
 */
class CrmTaskPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function view(Employee $actor, CrmTask $task): bool
    {
        return $actor->company_id === $task->company_id
            && ($actor->hasManagerRole('principal', 'rh', 'marketing') || $task->assigned_to === $actor->id);
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function update(Employee $actor, CrmTask $task): bool
    {
        return $actor->company_id === $task->company_id
            && ($actor->hasManagerRole('principal', 'rh', 'marketing') || $task->assigned_to === $actor->id);
    }

    public function delete(Employee $actor, CrmTask $task): bool
    {
        return $actor->company_id === $task->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }
}
