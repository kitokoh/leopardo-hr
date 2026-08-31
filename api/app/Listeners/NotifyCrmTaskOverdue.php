<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\CrmTaskOverdue;
use App\Modules\Notification\Domain\Models\Notification;

/**
 * Issue #5720 — Notification interne de relance de tâche CRM en retard.
 *
 * Consomme l'événement `CrmTaskOverdue` (payload scalaire, aucun modèle CRM
 * importé) et crée une ligne `notifications` (modèle canonique du module
 * Notification) pour l'assigné de la tâche. L'idempotence (une relance par
 * tâche et par jour) est garantie EN AMONT par la table
 * `crm_task_reminders` (contrainte UNIQUE) — ce listener est donc safe à
 * rejouer.
 */
class NotifyCrmTaskOverdue
{
    public function handle(CrmTaskOverdue $event): void
    {
        if ($event->assigneeId === null) {
            return;
        }

        Notification::create([
            'company_id' => $event->companyId,
            'employee_id' => $event->assigneeId,
            'type' => 'crm_task_overdue',
            'title' => 'Tâche CRM en retard',
            'body' => $event->title,
            'data' => [
                'task_id' => $event->taskId,
                'due_at' => $event->dueAtIso,
            ],
            'is_read' => false,
        ]);
    }
}
