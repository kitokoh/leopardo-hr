<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5720 — Relances internes idempotentes des tâches CRM en retard.
 *
 * `crm_task_reminders` journalise UNE relance par (tâche, jour) : le job
 * planifié `crm:tasks:send-overdue-reminders` détecte les tâches en retard
 * (status todo/in_progress, due_at < now) et n'émet une notification interne
 * que si aucune ligne n'existe pour (task_id, remind_date) — contrainte
 * UNIQUE en garde d'idempotence (re-run, retry, double worker → pas de doublon).
 *
 * Pas de FK vers `crm_tasks` : la table est créée par la fondation V0
 * (issue #5710, mergée en parallèle) — la colonne `task_id` reste une
 * référence logique indexée, documentée ici. `company_id` est requis pour
 * l'isolation tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('crm_task_reminders')) {
            return;
        }

        Schema::create('crm_task_reminders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('company_id')->index();
            // Référence logique vers crm_tasks.id (table créée par #5710).
            $table->unsignedBigInteger('task_id')->index();
            // Date locale entreprise du rappel (fuseau tenant).
            $table->date('remind_date');
            $table->timestampTz('created_at')->useCurrent();

            // Idempotence : une relance par tâche et par jour.
            $table->unique(['task_id', 'remind_date'], 'crm_task_reminders_task_date_unique');
        });

        DB::statement("COMMENT ON TABLE crm_task_reminders IS 'Relances internes des tâches CRM en retard : une ligne par (tâche, jour) — garde d''idempotence des notifications (issue #5720).'");
    }

    public function down(): void
    {
        $schema = resolveTableSchema('crm_task_reminders');
        if ($schema !== null) {
            Schema::dropIfExists("{$schema}.crm_task_reminders");
        }
    }
};
