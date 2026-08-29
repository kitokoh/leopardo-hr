<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Events\CrmTaskOverdue;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5720 — Relances internes des tâches CRM en retard (idempotentes).
 *
 * Parcours les tâches en retard (status `todo|in_progress`, `due_at` passé)
 * de TOUS les tenants (exécution planifiée, hors surface API) et émet
 * l'événement `CrmTaskOverdue` UNE fois par (tâche, jour) : la table
 * `crm_task_reminders` porte une contrainte UNIQUE (task_id, remind_date),
 * `insertOrIgnore` ne renvoie de count que pour les lignes réellement
 * insérées — un re-run, un retry ou deux workers ne créent jamais de doublon.
 *
 * `remind_date` est calculée dans le fuseau du tenant (companies.timezone),
 * défaut UTC — la comparaison `due_at < now()` est faite en UTC (instants,
 * indépendant du fuseau).
 */
class CrmOverdueReminderService
{
    /** @return int Nombre de relances nouvellement émises. */
    public function run(): int
    {
        $now = Carbon::now();

        /** @var \Illuminate\Support\Collection<int, CrmTask> $tasks */
        $tasks = CrmTask::query()
            ->withoutGlobalScope('company')
            ->whereIn('status', ['todo', 'in_progress'])
            ->where('due_at', '<', $now)
            ->get(['id', 'company_id', 'assigned_to', 'title', 'due_at']);

        $emitted = 0;

        foreach ($tasks as $task) {
            $remindDate = $this->tenantRemindDate((string) $task->company_id, $now);

            $inserted = DB::table('crm_task_reminders')->insertOrIgnore([
                'company_id' => $task->company_id,
                'task_id' => $task->id,
                'remind_date' => $remindDate,
                'created_at' => $now,
            ]);

            if ($inserted === 0) {
                continue; // déjà relancée aujourd'hui — idempotent.
            }

            CrmTaskOverdue::dispatch(
                (int) $task->company_id,
                (int) $task->id,
                $task->assigned_to !== null ? (int) $task->assigned_to : null,
                (string) $task->title,
                $task->due_at?->toIso8601String(),
            );

            $emitted++;
        }

        return $emitted;
    }

    private function tenantRemindDate(string $companyId, Carbon $now): string
    {
        /** @var string|null $timezone */
        $timezone = Company::query()->whereKey($companyId)->value('timezone');

        return $now->copy()->timezone($timezone ?? 'UTC')->toDateString();
    }
}
