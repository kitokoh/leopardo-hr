<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5710 — CRM client V0 : timeline (activities) + tâches + ownership.
 *
 * - `crm_activities` : journal de timeline **append-only** (note, appel,
 *   email, réunion, transition...) rattaché à un compte/contact/lead/
 *   opportunité du MÊME tenant. Les mutations ne passent qu'en INSERT au
 *   niveau application (pas de PUT/DELETE exposé) ; `occurred_at` indexé
 *   pour la pagination temporelle.
 * - `crm_tasks` : tâches bornées (titre limité, statuts/priorités CHECK),
 *   `assigned_to` (ownership) + `due_at`/`completed_at` indexés.
 *
 * Les colonnes de rattachement (`account_id`, `contact_id`, `lead_id`,
 * `opportunity_id`) sont indexées SANS FK : les tables correspondantes
 * arrivent dans d'autres PR V0 (#5708/#5709) — la cohérence du tenant est
 * validée au niveau application/Policies (pattern payroll_payment_orders).
 *
 * Migration idempotente (garde #1962/#5431), réf. issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('opportunity_id')->nullable();
                $table->string('type', 40);
                $table->string('subject', 200)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'occurred_at'], 'crm_activities_company_occurred_idx');
                $table->index(['company_id', 'account_id', 'occurred_at'], 'crm_activities_company_account_occurred_idx');
                $table->index(['lead_id'], 'crm_activities_lead_idx');
                $table->index(['opportunity_id'], 'crm_activities_opportunity_idx');
                $table->index(['created_by'], 'crm_activities_created_by_idx');
            });

            // Timeline bornée : types d'activité allowlistés.
            // (Blueprint::check indisponible dans cette version → SQL brut, pattern #5234.)
            DB::statement(
                "ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_type_check CHECK (type IN ('note', 'call', 'email', 'meeting', 'task_created', 'task_completed', 'stage_changed', 'status_changed', 'system'))"
            );
        }

        if (! schemaTableExists('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('opportunity_id')->nullable();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('todo');
                $table->string('priority', 10)->default('medium');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'crm_tasks_company_status_idx');
                $table->index(['company_id', 'due_at'], 'crm_tasks_company_due_idx');
                $table->index(['assigned_to'], 'crm_tasks_assigned_idx');
                $table->index(['lead_id'], 'crm_tasks_lead_idx');
                $table->index(['opportunity_id'], 'crm_tasks_opportunity_idx');
            });

            DB::statement(
                "ALTER TABLE crm_tasks ADD CONSTRAINT crm_tasks_status_check CHECK (status IN ('todo', 'in_progress', 'done', 'cancelled'))"
            );
            DB::statement(
                "ALTER TABLE crm_tasks ADD CONSTRAINT crm_tasks_priority_check CHECK (priority IN ('low', 'medium', 'high'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
    }
};
