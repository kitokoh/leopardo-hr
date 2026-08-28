<?php

declare(strict_types=1);

namespace App\Modules\CRM\Console\Commands;

use App\Modules\CRM\Application\Services\CrmOverdueReminderService;
use Illuminate\Console\Command;

/**
 * Issue #5720 — Envoi planifié des relances internes de tâches CRM en retard.
 *
 * Planifié dans `routes/console.php` toutes les 30 minutes. Idempotent par
 * construction (table `crm_task_reminders`, UNIQUE task_id+remind_date) —
 * le command peut être rejoué sans risque de doublon.
 */
class CrmSendOverdueTaskReminders extends Command
{
    protected $signature = 'crm:tasks:send-overdue-reminders';

    protected $description = 'Emet les relances internes des tâches CRM en retard (une par tâche et par jour).';

    public function handle(CrmOverdueReminderService $service): int
    {
        $count = $service->run();

        $this->info("Relances CRM émises : {$count}.");

        return self::SUCCESS;
    }
}
