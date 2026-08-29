<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5710 — CRM client V0 : timeline (activities) + tâches (tenant-scoped).
 *
 * - `crm_activities` : journal de timeline **append-only** (note, appel,
 *   email, réunion, transition...) rattaché à un compte/contact/lead/
 *   opportunité du MÊME tenant. Les mutations ne passent qu'en INSERT au
 *   niveau application ; `occurred_at` indexé pour la pagination temporelle.
 * - `crm_tasks` : tâches alignées sur le schéma inline main
 *   (CrmPilotSeederTest) et sur les seeders #5743 (`CrmPilotSeeder` /
 *   `CrmBenchmarkSeeder`) : `title`, `description`, `due_at`, `assignee_id`
 *   (ownership) et `done` (booléen). L'ancien schéma de l'ère pré-main
 *   (status/priority/assigned_to/completed_at) est abandonné — la simplicité
 *   V0 portée par main fait foi.
 *
 * Les colonnes de rattachement de `crm_activities` (`account_id`,
 * `contact_id`, `lead_id`, `opportunity_id`) sont indexées SANS FK : les
 * tables correspondantes arrivent dans d'autres PR V0 (#5708/#5709) — la
 * cohérence du tenant est validée au niveau application/Policies.
 *
 * Migration idempotente (garde #1962/#5431, pattern schemaTableExists), réf.
 * issue dans le nom.
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

            DB::statement("COMMENT ON TABLE crm_activities IS 'Timeline CRM client append-only : types allowlistés (CHECK), rattachements sans FK, pagination temporelle (#5710).'");
        }

        if (! schemaTableExists('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->timestampTz('due_at')->nullable();
                $table->unsignedBigInteger('assignee_id')->nullable();
                $table->boolean('done')->default(false);
                $table->timestamps();

                $table->index(['company_id', 'due_at'], 'crm_tasks_company_due_idx');
                $table->index(['company_id', 'done'], 'crm_tasks_company_done_idx');
                $table->index(['assignee_id'], 'crm_tasks_assignee_idx');
            });

            DB::statement("COMMENT ON TABLE crm_tasks IS 'Tâches CRM client : ownership (assignee_id), échéance (due_at), done booléen — aligné seeders #5743 (#5710).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
    }
};
